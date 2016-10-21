<?php

namespace Lunches\Actualizer\Synchronizer;

use Google_Service_Sheets;
use GuzzleHttp\Exception\ClientException;
use Lunches\Actualizer\Service\MenusService;
use Lunches\Actualizer\Service\UsersService;
use Lunches\Actualizer\ValueObject\WeekDays;
use Monolog\Logger;
use GuzzleHttp\Client;
use Webmozart\Assert\Assert;

/**
 * Class OrdersSynchronizer.
 */
class OrdersSynchronizer
{
    /** @var Logger */
    private $logger;

    const BIG = 'Большая';
    const BIG_NO_MEAT = 'Большая без мяса';
    const BIG_NO_SALAD = 'Большая без салата';
    const BIG_NO_GARNISH= 'Большая без гарнира';
    const MEDIUM = 'Средняя';
    const MEDIUM_NO_MEAT = 'Средняя без мяса';
    const MEDIUM_NO_SALAD = 'Средняя без салата';
    const MEDIUM_NO_GARNISH= 'Средняя без гарнира';
    const ONLY_MEAT = 'Только мясо';
    const ONLY_SALAD = 'Только салат';
    const ONLY_GARNISH = 'Только гарнир';

    private static $orderVariants = [
        self::BIG,
        self::BIG_NO_MEAT,
        self::BIG_NO_SALAD,
        self::BIG_NO_GARNISH,
        self::MEDIUM,
        self::MEDIUM_NO_MEAT,
        self::MEDIUM_NO_SALAD,
        self::MEDIUM_NO_GARNISH,
        self::ONLY_MEAT,
        self::ONLY_SALAD,
        self::ONLY_GARNISH,
    ];

    /**
     * Cache property which stores map of 'order string' to menu dishes
     *
     * Example:
     * [
     *     '03.10.2016' => [
     *         'Большая' => ['dishes' => [...]],
     *         'Средняя без мяса' => ... ,
     *     ],
     *     ...
     * ]
     * @var array
     */
    private $lastWeekMenuVariants;
    /**
     * @var string
     */
    private $lunchesApiDateFormat = 'Y-m-d';
    /**
     * @var WeekDays
     */
    private $lastWeekDays;
    /**
     * @var Google_Service_Sheets
     */
    private $sheetsService;
    /**
     * @var MenusService
     */
    private $menusService;
    /**
     * @var UsersService
     */
    private $usersService;

    /**
     * OrdersSynchronizer constructor.
     *
     * @param Google_Service_Sheets $sheetsService
     * @param MenusService $menusService
     * @param UsersService $usersService
     * @param Logger $logger
     * @internal param string $weekStr
     */
    public function __construct(
        Google_Service_Sheets $sheetsService,
        MenusService $menusService,
        UsersService $usersService,
        Logger $logger)
    {
        $this->logger = $logger;
        $this->sheetsService = $sheetsService;
        $this->menusService = $menusService;
        $this->usersService = $usersService;
    }

    public function sync($spreadsheetId, $sheetRange)
    {
        foreach ($this->readWeeks($spreadsheetId, $sheetRange) as list($dateRange, $weekOrders)) {

            try {
                $this->lastWeekDays = new WeekDays($dateRange);
                $this->lastWeekMenuVariants = $this->generateMenuVariants($this->fetchWeekMenus());

                $orders = $this->createFromRange($weekOrders);

                $this->syncList($orders);
            } catch (\Exception $e) {
                $this->logger->addError("Can't sync week orders due to: ". $e->getMessage());
                continue;
            }
        }
    }

    /**
     * Example of $range:
     * [
     *     ['User name1', 'monday order', 'Средняя', 'Большая без салата', ...],
     *     ...
     * ]
     * @param array $range
     * @return \Generator
     * @throws \Exception
     */
    private function createFromRange(array $range)
    {
        $range = array_filter($range, function ($item) {
            return is_array($item) && $item;
        });

        $lastAddress = null;
        foreach ($range as $userWeekOrders) {
            $userName = array_shift($userWeekOrders);
            if (0 === strpos($userName, 'Floor')) {
                $lastAddress = $userName;
                continue;
            }
            try {
                $user = $this->getUser($userName, $lastAddress);
                $orders = $this->createWeekOrders($user, $userWeekOrders);
                foreach ($orders as $order) {
                    yield $order;
                }
            } catch (\RuntimeException $e) {
                // TODO log
                continue;
            }
        }
    }
    /**
     * @param array $user
     * @param array $sourceOrders
     * @return array
     * @throws \Exception
     */
    private function createWeekOrders($user, $sourceOrders)
    {
        $orders = [];
        if (!$sourceOrders) {
            return $orders;
        }

        foreach ($sourceOrders as $i => $weekDayOrder) {
            if (!$weekDayOrder) {
                continue;
            }
            try {
                $orderDate = $this->lastWeekDays->at($i)->format($this->lunchesApiDateFormat);

                $orders[] = $this->constructOrder($user, $orderDate, $weekDayOrder);

            } catch (\Exception $e) {
                if ($e instanceof \InvalidArgumentException) {
                    $msg = sprintf("%s's Order on %s has not been created due to: %s", $user['fullname'], isset($orderDate) ? $orderDate:'', $e->getMessage());
                    $this->logger->addWarning($msg);
                    continue;
                }
                throw $e;
            }
        }

        return $orders;
    }
    private function syncList($sourceOrders)
    {
        foreach ($sourceOrders as $sourceOrder) {
            // TODO idempotent PUT, but what about order creation?
            try {
                $destOrder = $this->findOrder($sourceOrder);
                if (!$destOrder) {
                    $destOrder = $this->createOrder($sourceOrder);
                }
            } catch (ClientException $e) {
                $this->logger->addWarning("Can't sync order due to: ".$e->getMessage());
                continue;
            }
        }
    }
    private function generateMenuVariants(array $menus)
    {
        $variants = [];
        foreach ($menus as $weekDayMenu) {

            $withoutMeat = $this->removeMenuDishes($weekDayMenu, ['meat']);
            $withoutSalad = $this->removeMenuDishes($weekDayMenu, ['salad']);
            $withoutGarnish = $this->removeMenuDishes($weekDayMenu, ['garnish']);

            Assert::keyExists($weekDayMenu, 'date');

            $variants[$weekDayMenu['date']] = [
                self::BIG => $weekDayMenu,
                self::MEDIUM => $weekDayMenu,
                self::BIG_NO_MEAT => $withoutMeat,
                self::MEDIUM_NO_MEAT => $withoutMeat,
                self::BIG_NO_SALAD => $withoutSalad,
                self::MEDIUM_NO_SALAD => $withoutSalad,
                self::BIG_NO_GARNISH => $withoutGarnish,
                self::MEDIUM_NO_GARNISH => $withoutGarnish,
                self::ONLY_MEAT => $this->removeMenuDishes($weekDayMenu, ['garnish', 'salad']),
                self::ONLY_SALAD => $this->removeMenuDishes($weekDayMenu, ['garnish', 'meat']),
                self::ONLY_GARNISH => $this->removeMenuDishes($weekDayMenu, ['salad', 'meat']),
            ];
        }

        return $variants;
    }

    /**
     * Find or register user by userName
     *
     * @param string $userName
     * @param string $address
     * @return string User ID in UUID format
     * @throws \RuntimeException
     */
    private function getUser($userName, $address)
    {
        $user = $this->usersService->findOne($userName);

        if (!$user) {
            $user = $this->usersService->create($userName, $address);
        }
        if (!$user['address']) {
            $user['address'] = $address;
        }

        return $user;
    }

    /**
     * @return array
     */
    private function fetchWeekMenus()
    {
        return $this->menusService->findBetween(
            $this->lastWeekDays->first(),
            $this->lastWeekDays->last()
        );
    }

    private function removeMenuDishes($menu, array $dishTypes)
    {
        Assert::keyExists($menu, 'dishes');

        /** @var array $dishes */
        $dishes = $menu['dishes'];
        foreach ($dishes as $key => $dish) {

            Assert::keyExists($dish, 'type');

            if (in_array($dish['type'], $dishTypes, false)) {
                unset($menu['dishes'][$key]);
            }
        }
        return $menu;
    }




    private function createOrder(array $sourceOrder)
    {
        // TODO refactor to use one client per class
        $client = new Client(['base_uri' => 'http://lunches-api.local/']);
        $response = $client->request('POST', '/orders', [
            'json' => $sourceOrder,
        ]);
        $body = (string) $response->getBody();

        return (array) json_decode($body, true);
    }

    private function findOrder($sourceOrder)
    {
        // TODO https://github.com/lunches-platform/api/issues/145
        return null;
    }


    private function getOrderedDishes($orderDate, $userOrder)
    {
        Assert::oneOf($userOrder, self::$orderVariants);
        Assert::keyExists($this->lastWeekMenuVariants, $orderDate, 'Menus not found for '.$orderDate);

        $allMenus = $this->lastWeekMenuVariants[$orderDate];
        Assert::keyExists($allMenus, $userOrder, "Menu is not available for '{$userOrder}'");

        $orderedMenu = $allMenus[$userOrder];
        Assert::keyExists($orderedMenu, 'dishes');

        $dishes = $orderedMenu['dishes'];
        Assert::isArray($dishes);
        Assert::notEmpty($dishes);

        return $dishes;
    }

    /**
     * @param array $user
     * @param string $shipmentDate
     * @param string $orderStr
     * @return array|null
     *
     */
    private function constructOrder($user, $shipmentDate, $orderStr)
    {
        return [
            'items' => $this->constructLineItems($orderStr, $shipmentDate),
            'userId' => $user['id'],
            'address' => $user['address'],
            'shipmentDate' => $shipmentDate,
        ];
    }

    private function constructLineItems($orderStr, $shipmentDate)
    {
        $size = 'medium';
        if (in_array($orderStr, [ self::BIG, self::BIG_NO_MEAT, self::BIG_NO_SALAD, self::BIG_NO_GARNISH ], true)) {
            $size = 'big';
        }

        $dishes = $this->getOrderedDishes($shipmentDate, $orderStr);

        return array_map(function ($dish) use ($size) {
            return [
                'dishId' => $dish['id'],
                'size' => $size,
            ];
        }, $dishes);
    }
    /**
     * @param string $spreadsheetId
     * @param string $sheetRange
     * @return \Generator
     */
    private function readWeeks($spreadsheetId, $sheetRange)
    {
        $response = $this->sheetsService->spreadsheets->get($spreadsheetId);
        $sheets = $response->getSheets();

        if (!count($sheets)) {
            yield;
        }

        // just read the first currently
        $sheet = array_shift($sheets);
        $weekDateRange = $sheet->getProperties()->getTitle();
        $range = $weekDateRange.'!'.$sheetRange;
        $response = $this->sheetsService->spreadsheets_values->get($spreadsheetId, $range);

        /** @var array $weekOrders */
        $weekOrders = $response->getValues();

        yield [ $weekDateRange, $weekOrders ];
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
}