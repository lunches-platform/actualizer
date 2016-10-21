<?php

namespace Lunches\Actualizer\Synchronizer;

use Google_Service_Sheets;
use GuzzleHttp\Exception\ClientException;
use Lunches\Actualizer\Entity\Menu;
use Lunches\Actualizer\Entity\Order;
use Lunches\Actualizer\Service\MenusService;
use Lunches\Actualizer\Service\OrdersService;
use Lunches\Actualizer\Service\UsersService;
use Lunches\Actualizer\ValueObject\WeekDays;
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

    public function sync($spreadsheetId, $sheetRange)
    {
        foreach ($this->readWeeks($spreadsheetId, $sheetRange) as list($dateRange, $weekOrders)) {
            try {
                $weekMenus = $this->getWeekMenus(new WeekDays($dateRange));
                $orders = $this->createFromRange($weekOrders, $weekMenus);
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
     *     ['User name1', 'monday order str', 'Средняя', 'Большая без салата', ...],
     *     ...
     * ]
     * Range is a matrix, where first column contains users and next 1-5 columns contain user orders for the week
     *
     * @param array $range
     * @param array $weekMenus
     * @return \Generator
     * @throws \Exception
     */
    private function createFromRange(array $range, array $weekMenus)
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
                $userOrders = $this->createWeekOrders($user, $userWeekOrders, $weekMenus);
                foreach ($userOrders as $order) {
                    yield $order;
                }
            } catch (\Exception $e) {
                $this->logger->addError("Can't create {$userName}'s week orders due to: ". $e->getMessage());
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
     * @return \Generator
     * @throws \Exception
     */
    private function createWeekOrders($user, $strOrders, array $menus)
    {
        if (!$strOrders) {
            yield;
        }

        foreach ($strOrders as $i => $weekDayOrder) {
            // user can skip several days
            if (!$weekDayOrder) {
                continue;
            }
            try {
                $menu = $this->getWeekDayMenu($menus, $i);
                $order = new Order($menu->date(true), $user['id'], $user['address']);
                $order->setItemsFromOrderString($menu, $weekDayOrder);
                unset($menu);

                yield $order;

            } catch (\Exception $e) {
                if ($e instanceof \InvalidArgumentException) {
                    $date = isset($menu) ? $menu->date(true) : '';
                    $msg = sprintf("%s's Order on %s has not been created due to: %s", $user['fullname'], $date, $e->getMessage());
                    $this->logger->addWarning($msg);
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * @param Menu[] $menus
     * @param int $weekDayIndex Index from 0 to 4
     * @return Menu
     */
    private function getWeekDayMenu($menus, $weekDayIndex)
    {
        Assert::keyExists($menus, $weekDayIndex);
        Assert::isInstanceOf($menus[$weekDayIndex], Menu::class, 'Menu not found');

        return $menus[$weekDayIndex];
    }

    /**
     * Get array of strongly 5 values, when there is no menu - leave value as NULL
     *
     * @param WeekDays $weekDays
     * @return array
     */
    private function getWeekMenus(WeekDays $weekDays)
    {
        $menus = $this->menusService->findBetween(
            $weekDays->first(),
            $weekDays->last()
        );
        $weekMenus = array_fill(0, 5, null);
        foreach ($weekMenus as $i => &$value) {
            foreach ($menus as $menu) {
                if ($menu->date()->format('w') - 1 === $i) {
                    $value = $menu;
                }
            }
        }
        return $weekMenus;
    }

    /**
     * @param \Generator $orders
     */
    private function syncList($orders)
    {
        foreach ($orders as $order) {
            // TODO idempotent PUT, but what about order creation?
            /** @var $order Order */
            try {
                $existentOrder = $this->ordersService->findOne($order);
                if (!$existentOrder) {
                    $this->ordersService->create($order);
                    $this->logger->addInfo("Order on {$order->date(true)} of user #{$order->userId()} is created");
                }
            } catch (ClientException $e) {
                $this->logger->addWarning("Can't sync user #{$order->userId()} order on {$order->date(true)} due to: ".$e->getMessage());
                continue;
            }
        }
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
        // TODO implement UserSynchronizer
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