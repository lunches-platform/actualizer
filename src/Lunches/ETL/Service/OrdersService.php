<?php


namespace Lunches\ETL\Service;

use GuzzleHttp\Exception\ClientException;
use Lunches\ETL\Entity\LineItem;
use Lunches\ETL\Entity\Order;

class OrdersService extends AbstractService
{
    public function find(Order $order)
    {
        try {
            $username = rawurlencode($order->user()['fullname']);
            $orders = $this->makeRequest('GET', "/users/{$username}/orders", [
                'query' => [
                    'shipmentDate' => $order->date(true),
                    'items' => array_map(function (LineItem $lineItem) {
                        return $lineItem->toArray();
                    }, $order->lineItems()),
                ]
            ]);
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                return [];
            }
            throw $e;
        }
        $orders = array_map([$this, 'fromArray'], $orders);

        return $orders;
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