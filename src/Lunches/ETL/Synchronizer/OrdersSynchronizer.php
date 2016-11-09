<?php

namespace Lunches\ETL\Synchronizer;

use Google_Service_Sheets;
use GuzzleHttp\Exception\ClientException;
use Lunches\ETL\Entity\Menu;
use Lunches\ETL\Entity\Order;
use Lunches\ETL\Service\MenusService;
use Lunches\ETL\Service\OrdersService;
use Lunches\ETL\Service\UsersService;
use Lunches\ETL\ValueObject\Address;
use Lunches\ETL\ValueObject\WeekDays;
use Monolog\Logger;
use Webmozart\Assert\Assert;

/**
 * Class OrdersSynchronizer.
 */
class OrdersSynchronizer
{
    /** @var Logger */
    private $logger;
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
     * @var OrdersService
     */
    private $ordersService;

    /**
     * OrdersSynchronizer constructor.
     *
     * @param Google_Service_Sheets $sheetsService
     * @param MenusService $menusService
     * @param UsersService $usersService
     * @param OrdersService $ordersService
     * @param Logger $logger
     */
    public function __construct(
        Google_Service_Sheets $sheetsService,
        MenusService $menusService,
        UsersService $usersService,
        OrdersService $ordersService,
        Logger $logger)
    {
        $this->logger = $logger;
        $this->sheetsService = $sheetsService;
        $this->menusService = $menusService;
        $this->usersService = $usersService;
        $this->ordersService = $ordersService;
    }

    public function sync($spreadsheetId, $sheetRange, array $filters = [])
    {
        foreach ($this->extract($spreadsheetId, $sheetRange, $filters) as list($dateRange, $menuType, $rangeOrders)) {
            if (array_key_exists('menuType', $filters) && $filters['menuType'] !== $menuType) {
                continue;
            }
            $this->logger->addInfo("Start sync {$dateRange} week...");

            try {
                $weekMenus = $this->getWeekMenus(new WeekDays($dateRange), $menuType);
                $weekOrders = $this->transformRange($rangeOrders);
                $orders = $this->buildWeekOrders($weekOrders, $weekMenus);
                $this->load($orders);
            } catch (\Exception $e) {
                $this->logger->addError("Can't sync week orders due to: ". $e->getMessage());
                continue;
            }
        }
    }

    /**
     * Example of $range:
     * [
     *     ['User name1', 'monday order str', 'Средняя', 'Большая без салата', ...],
     *     ...
     * ]
     * Range is a matrix, where first column contains users and next 1-5 columns contain user orders for the week
     *
     * @param array $range
     * @return array
     * @throws \Exception
     */
    private function transformRange(array $range)
    {
        $this->logger->addInfo('Transform orders');
        $range = array_filter($range, function ($item) {
            return is_array($item) && $item;
        });

        $lastAddress = null;
        $userOrders = [];
        foreach ($range as $userWeekOrders) {
            $userName = array_shift($userWeekOrders);
            if (0 === strpos($userName, 'Floor')) {
                $lastAddress = $userName;
                continue;
            }

            try {
                if (!array_key_exists($userName, $userOrders)) {
                    $userOrders[$userName] = [
                        'user' => $this->getUser($userName, $lastAddress),
                        'orders' => [ 'Mon' => [], 'Tue' => [], 'Wed' => [], 'Thu' => [], 'Fri' => [], ]
                    ];
                }
            } catch (\Exception $e) {
                $this->logger->addError("Can't transform {$userName}'s week orders due to: ". $e->getMessage());
                continue;
            }

            $userWeekOrders = array_pad($userWeekOrders, 5, null);

            /** @var array $orders */
            $orders = &$userOrders[$userName]['orders'];
            foreach ($orders as $weekday => $weekdayOrders) {

                /** @noinspection DisconnectedForeachInstructionInspection */
                $order = array_shift($userWeekOrders);
                if (!$order) {
                    continue;
                }
                if (!array_key_exists($order, $orders[$weekday])) {
                    $orders[$weekday][$order] = 0;
                }
                $orders[$weekday][$order]++;
            }
        }

        return $userOrders;
    }

    /**
     * @param array $weekOrders
     * @param array $weekMenus
     * @return \Generator
     */
    private function buildWeekOrders($weekOrders, array $weekMenus)
    {
        foreach ($weekOrders as $userWeekOrders) {
            $user = $userWeekOrders['user'];
            $userOrders = $userWeekOrders['orders'];

            try {
                $userOrders = $this->buildUserOrders($user, $userOrders, $weekMenus);
                $this->logger->addInfo(sprintf('User %s made %s orders this week', $user['fullname'], count($userOrders)));

                foreach ($userOrders as $order) {
                    yield $order;
                }
            } catch (\Exception $e) {
                $this->logger->addError("Can't create {$user['fullname']}'s week orders due to: ". $e->getMessage());
                continue;
            }
        }
    }

    /**
     * Create all user orders for all week
     *
     * @param array $user
     * @param array $strOrders
     * @param Menu[] $menus
     * @return array
     * @throws \Exception
     */
    private function buildUserOrders($user, $strOrders, array $menus)
    {
        $userOrders = [];
        foreach ($strOrders as $weekday => $weekDayOrders) {
            try {
                $menu = $this->getWeekDayMenu($menus, $weekday);
                $weekDayOrders = array_filter($weekDayOrders);

                $orderProto = new Order($menu->date(true), $user, new Address($user, 'Kiev', $user['address'], $user['company']));

                /** @var array $weekDayOrders */
                foreach ($weekDayOrders as $weekDayOrder => $quantity) {
                    $order = clone $orderProto;
                    $order->setItemsFromOrderString($menu, $weekDayOrder);
                    $order->addQuantity($quantity);

                    $userOrders[] =  $order;
                }
            } catch (\Exception $e) {
                if ($e instanceof \InvalidArgumentException) {
                    $msg = sprintf("%s's Order on %s has not been created due to: %s", $user['fullname'], $weekday, $e->getMessage());
                    $this->logger->addWarning($msg);
                    continue;
                }
                throw $e;
            }
        }

        return $userOrders;
    }

    /**
     * @param Menu[] $menus
     * @param int $weekday String representation of the day of the week
     * @return Menu
     */
    private function getWeekDayMenu($menus, $weekday)
    {
        Assert::keyExists($menus, $weekday);
        Assert::isInstanceOf($menus[$weekday], Menu::class, 'Menu not found for '.$weekday);

        return $menus[$weekday];
    }

    /**
     * Get array of strongly 5 values, when there is no menu - leave value as NULL
     *
     * @param WeekDays $weekDays
     * @param string $menuType
     * @return array
     */
    private function getWeekMenus(WeekDays $weekDays, $menuType)
    {
        $menus = $this->menusService->findBetween(
            $weekDays->first(),
            $weekDays->last()
        );
        $menus = array_filter($menus, function (Menu $menu) use ($menuType) {
            return $menu->isType($menuType);
        });
        $weekMenus = [ 'Mon' => null, 'Tue' => null, 'Wed' => null, 'Thu' => null, 'Fri' => null, ];
        foreach ($menus as $menu) {
            $weekday = $menu->date()->format('D');
            if (array_key_exists($weekday, $weekMenus)) {
                $weekMenus[$weekday] = $menu;
            }
        }
        return $weekMenus;
    }

    /**
     * @param \Generator $orders
     */
    private function load($orders)
    {
        /** @var $orders Order[] */
        foreach ($orders as $order) {
            $orderDate = $order->date()->format('D Y-m-d');
            $orderUser = $order->user()['fullname'];
            try {
                $existentOrders = $this->ordersService->find($order);
                $cntExistent = count($existentOrders);

                if ($cntExistent > 0 && $cntExistent < $order->quantity()) {
                    $this->logger->addInfo("User {$orderUser} ordered {$order->quantity()} portions for {$orderDate}, but have only {$cntExistent}, lets create remaining...");
                } elseif ($cntExistent > $order->quantity()) {
                    $this->logger->addWarning("User {$orderUser} ordered {$order->quantity()} portions for {$orderDate}, but have as many as {$cntExistent} portions, skip but it is strange...");
                } elseif ($cntExistent && $cntExistent === $order->quantity()) {
                    $this->logger->addInfo("{$orderUser} order for {$orderDate} exists, skip");
                } else {
                    $this->logger->addInfo("There are no {$orderUser}'s orders for {$orderDate}. Creating ...");
                }

                for ($i = count($existentOrders); $i < $order->quantity(); $i++) {
                    $this->ordersService->create($order);
                    $this->logger->addInfo("Order on {$order->date(true)} of user {$order->user()['fullname']} is created");
                }
            } catch (ClientException $e) {
                $this->logger->addWarning("Can't sync user {$orderUser} order on {$orderDate} due to: ".$e->getMessage());
                continue;
            }
        }
    }

    /**
     * Find user by userName
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
            throw new \RuntimeException("User {$userName} not found");
        }
        if (!$user['address']) {
            $user['address'] = $address;
        }

        return $user;
    }

    /**
     * @param string $spreadsheetId
     * @param string $sheetRange
     * @param array $filters
     * @return \Generator
     */
    private function extract($spreadsheetId, $sheetRange, array $filters = [])
    {
        $response = $this->sheetsService->spreadsheets->get($spreadsheetId);
        $sheets = $response->getSheets();

        $menuType = $this->determineMenuType($response->getProperties()->getTitle());

        if (!count($sheets)) {
            yield;
        }

        /** @var \Google_Service_Sheets_Sheet[] $sheets */
        foreach ($sheets as $sheet) {

            $title = $sheet->getProperties()->getTitle();
            $weekDateRange = $this->getDateRange($title);
            if (array_key_exists('weekRange', $filters) && $filters['weekRange'] !== $weekDateRange) {
                continue;
            }
            $range = $title.'!'.$sheetRange;

            $response = $this->sheetsService->spreadsheets_values->get($spreadsheetId, $range);


            /** @var array $weekOrders */
            $weekOrders = $response->getValues();

            yield [ $weekDateRange, $menuType, $weekOrders ];
        }
    }
    private function getDateRange($sheetTitle)
    {
        return trim(str_replace('- diet', '', mb_strtolower($sheetTitle)));
    }
    private function determineMenuType($sheetTitle)
    {
        return mb_stripos($sheetTitle, 'diet') ? 'diet' : 'regular';
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
}