<?php


namespace Lunches\Actualizer\Service;

use Lunches\Actualizer\Entity\Order;

class OrdersService extends AbstractService
{
    public function findOne(Order $order)
    {
        $orders = $this->makeRequest('GET', "/users/{$order->user()['fullname']}/orders", [
            'query' => [
                'shipmentDate' => $order->date(true),
                'items' => $order->lineItems(),
            ]
        ]);
        $orders = array_map([$this, 'fromArray'], $orders);

        return array_shift($orders);
    }
    /**
     * @param \DateTimeImmutable $startDate
     * @param \DateTimeImmutable $endDate
     * @return Order[]
     */
    public function findBetween(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        $orders = $this->makeRequest('GET', '/orders', [
            'query' => [
                'startDate' => $startDate->format($this->apiDateFormat),
                'endDate' => $endDate->format($this->apiDateFormat),
                'paid' => 1,
            ]
        ]);
        $orders = array_map([$this, 'fromArray'], $orders);

        return $orders;
    }

    public function create(Order $order)
    {
        return $this->makeRequest('POST', '/orders', [
            'json' => [
                'userId' => $order->user()['id'],
                'shipmentDate' => $order->date(true),
                'address' => $order->address()->toString(),
                'items' => $order->lineItems(),
            ],
        ]);
    }
    private function fromArray(array $order)
    {
        $order['company'] = $this->company;
        return Order::fromArray($order);
    }
}