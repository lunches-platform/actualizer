<?php


namespace Lunches\Actualizer\Service;

class OrdersService extends AbstractService
{
    public function findOne(array $order)
    {
        // TODO https://github.com/lunches-platform/api/issues/145
        return null;
    }

    public function create(array $order)
    {
        return $this->makeRequest('POST', '/users', [
            'json' => $order,
        ]);
    }
}