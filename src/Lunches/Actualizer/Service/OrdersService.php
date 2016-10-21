<?php


namespace Lunches\Actualizer\Service;

use Lunches\Actualizer\Entity\Order;

class OrdersService extends AbstractService
{
    public function findOne(Order $order)
    {
        // TODO https://github.com/lunches-platform/api/issues/145
        return null;
    }

    public function create(Order $order)
    {
        return $this->makeRequest('POST', '/orders', [
            'json' => $order,
        ]);
    }
}