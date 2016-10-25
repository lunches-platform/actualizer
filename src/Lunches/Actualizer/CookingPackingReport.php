<?php


namespace Lunches\Actualizer;

use League\Plates\Engine;
use Lunches\Actualizer\Entity\Order;
use Lunches\Actualizer\Service\OrdersService;

class CookingPackingReport
{
    /** @var OrdersService[] */
    private $ordersServices;
    /** @var  Engine */
    private $templates;

    public function __construct($ordersServices, Engine $engine)
    {
        $this->ordersServices = $ordersServices;
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
        foreach ($this->fetchOrders() as $order) {
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

            // TODO set menu type
//            $menuType = $order['menuType'];
            $menuType = 'regular';

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

        return $this->render($ordersTree);
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
     * @return \Generator
     */
    private function fetchOrders()
    {
        if (new \DateTimeImmutable('now') > new \DateTimeImmutable('friday + 13 hours')) {
            $startDate = new \DateTimeImmutable('monday next week');
            $endDate = new \DateTimeImmutable('friday next week');
        } else {
            $startDate = new \DateTimeImmutable('monday this week');
            $endDate = new \DateTimeImmutable('friday this week');
        }

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

    private function render($ordersTree)
    {
        return $this->templates->render('report', ['ordersTree' => $ordersTree]);
    }
}