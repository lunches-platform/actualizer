<?php


namespace Lunches\Actualizer;

use League\Plates\Engine;
use Lunches\Actualizer\Entity\Order;
use Lunches\Actualizer\Service\MenusService;
use Lunches\Actualizer\Service\OrdersService;

class CookingPackingReport
{
    /** @var OrdersService[] */
    private $ordersServices;
    /** @var MenusService[] */
    private $menusServices;
    /** @var  Engine */
    private $templates;
    /** @var array */
    private $menus = [];

    public function __construct($ordersServices, $menusServices, Engine $engine)
    {
        $this->ordersServices = $ordersServices;
        $this->menusServices = $menusServices;
        $this->templates = $engine;
        setlocale(LC_ALL, 'ru_RU.UTF-8');
    }

    /**
     * @return string
     *
     * Example of data structure:
     * ```
     * $data = [
     *     [
     *         'name' => '2016-10-21',
     *         'count' => 60,
     *         'subGroups' => [],
     *     ],
     *     [
     *         'name' => '2016-10-22',
     *         'count' => 33,
     *         'subGroups' => [
     *             [
     *                 'name' => 'company name',
     *                 'count' => 33,
     *                 'orders' => [],
     *             ],
     *         ]
     *     ],
     * ];
     * ```
     */
    public function forWeek()
    {
        $ordersTree = [];

        list ($startDate, $endDate) = $this->getWeekRange();

        $this->initMenus($startDate, $endDate);

        foreach ($this->fetchOrders($startDate, $endDate) as $order) {

            try {
                $this->addToTree($order, $ordersTree);
            } catch (\RuntimeException $e) {
                // TODO log
                continue;
            }
        }

        return $this->render($ordersTree);
    }

    private function getWeekRange()
    {
        if (new \DateTimeImmutable('now') > new \DateTimeImmutable('friday + 13 hours')) {
            $startDate = new \DateTimeImmutable('monday next week');
            $endDate = new \DateTimeImmutable('friday next week');
        } else {
            $startDate = new \DateTimeImmutable('monday this week');
            $endDate = new \DateTimeImmutable('friday this week');
        }

        return [$startDate, $endDate];
    }

    private function addToTree(Order $order, &$ordersTree)
    {
        /** @var Order $order */
        /** @var string $date */
        $date = $order->date(true);

        $dateGroup = $this->getGroup($ordersTree, $date);
        $dateGroup['count']++;
        $dateSubGroups = &$dateGroup['subGroups'];

        $company = $order->address()->company();

        $companyGroup = $this->getGroup($dateSubGroups, $company);
        $companyGroup['count']++;
        $companySubGroups = &$companyGroup['subGroups'];

        $address = $order->address()->street();

        $addressGroup = $this->getGroup($companySubGroups, $address);
        $addressGroup['count']++;
        $addressSubGroups = &$addressGroup['subGroups'];

        $menuType = $order->resolveMenuType($this->menus);

        $menuTypeGroup = $this->getGroup($addressSubGroups, $menuType);
        $menuTypeGroup['count']++;
        $menuTypeSubGroups = &$menuTypeGroup['subGroups'];

        $orderStr = $order->toDisplayString();

        $orderStrGroup = $this->getGroup($menuTypeSubGroups, $orderStr);
        $orderStrGroup['count']++;
        $this->addOrder($orderStrGroup, $order);

        $this->updateGroup($menuTypeSubGroups, $orderStr, $orderStrGroup);
        $this->updateGroup($addressSubGroups, $menuType, $menuTypeGroup);
        $this->updateGroup($companySubGroups, $address, $addressGroup);
        $this->updateGroup($dateSubGroups, $company, $companyGroup);
        $this->updateGroup($ordersTree, $date, $dateGroup);
    }

    /**
     * @param array $groups
     * @param string $name
     * @return array
     */
    private function getGroup($groups, $name)
    {
        foreach ($groups as $group) {
            if ($group['name'] === $name) {
                return $group;
            }
        }

        return [
            'name' => $name,
            'subGroups' => [],
            'count' => 0,
        ];
    }

    /**
     * @param array $groups
     * @param string $name
     * @param array $updatedGroup
     * @return bool
     */
    private function updateGroup(&$groups, $name, $updatedGroup)
    {
        foreach ($groups as &$group) {
            if ($group['name'] === $name) {
                $group = $updatedGroup;
                return true;
            }
        }
        unset($group);
        $groups[] = $updatedGroup;
        return true;
    }

    private function addOrder(&$group, Order $order)
    {
        if (!array_key_exists('orders', $group)) {
            $group['orders'] = [];
        }
        $group['orders'][] = $order;
    }

    /**
     * @param \DateTimeImmutable $startDate
     * @param \DateTimeImmutable $endDate
     * @return \Generator
     */
    private function fetchOrders(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        foreach ($this->ordersServices as $ordersService) {
            try {
                $orders = $ordersService->findBetween($startDate, $endDate);
                foreach ($orders as $order) {
                    yield $order;
                }
            } catch (\Exception $e) {
                // TODO log
                continue;
            }
        }
    }

    private function initMenus(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        foreach ($this->menusServices as $menusService) {
            try {
                $menus = $menusService->findBetween($startDate, $endDate);
                foreach ($menus as $menu) {
                    $this->menus[] = $menu;
                }
            } catch (\Exception $e) {
                // TODO log
                continue;
            }
        }
    }

    private function render($ordersTree)
    {
        return $this->templates->render('report', ['ordersTree' => $ordersTree]);
    }
}