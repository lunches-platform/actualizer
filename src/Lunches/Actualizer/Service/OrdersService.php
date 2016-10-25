<?php


namespace Lunches\Actualizer\Service;

use GuzzleHttp\Client;
use Lunches\Actualizer\Entity\Order;

class OrdersService extends AbstractService
{
    /** @var  string */
    private $company;

    public function __construct(Client $client, $accessToken, $company)
    {
        $this->company = $company;
        parent::__construct($client, $accessToken);
    }
    public function findOne(Order $order)
    {
        // TODO https://github.com/lunches-platform/api/issues/145
        return null;
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
            ]
        ]);
        $orders = array_map([$this, 'fromArray'], $orders);

        return $orders;
    }

    public function create(Order $order)
    {
        return $this->makeRequest('POST', '/orders', [
            'json' => $order,
        ]);
    }
    private function fromArray(array $order)
    {
        $order['company'] = $this->company;
        return Order::fromArray($order);
    }
}