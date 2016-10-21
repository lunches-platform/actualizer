<?php


namespace Lunches\Actualizer\Service;

use Webmozart\Assert\Assert;

class UsersService extends AbstractService
{
    public function findOne($userName)
    {
        return array_shift($this->makeRequest('GET', '/users', [
            'query' => ['like' => $userName],
        ]));
    }
    public function create($userName, $address)
    {
        Assert::string($userName);
        Assert::string($address);

        return $this->makeRequest('POST', '/users', [
            'json' => [
                'username' => $userName,
                'address' => $address,
            ]
        ]);
    }
}