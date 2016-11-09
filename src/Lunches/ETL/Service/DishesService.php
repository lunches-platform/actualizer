<?php


namespace Lunches\ETL\Service;

use Webmozart\Assert\Assert;

class DishesService extends AbstractService
{

    public function fetchAll()
    {
        return $this->makeRequest('GET', '/dishes');
    }

    public function find($name)
    {
        return $this->makeRequest('GET', '/dishes', [
            'query' => ['like' => $name],
        ]);
    }
    public function create($name, $type)
    {
        Assert::string($name);
        Assert::string($type);

        return $this->makeRequest('POST', '/dishes', [
            'json' => [
                'name' => $name,
                'type' => $type,
            ]
        ]);
    }
}