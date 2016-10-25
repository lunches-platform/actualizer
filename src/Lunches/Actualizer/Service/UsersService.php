<?php


namespace Lunches\Actualizer\Service;

use Webmozart\Assert\Assert;

class UsersService extends AbstractService
{
    public function findOne($userName)
    {
        $users = $this->find($userName);
        return array_shift($users);
    }

    public function find($userName)
    {
        $users = $this->makeRequest('GET', '/users', [
            'query' => ['like' => $userName],
        ]);
        $users = array_map([$this, 'prepareOne'], $users);
        return $users;
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

    private function prepareOne($item)
    {
        if (is_array($item)) {
            $item['company'] = $this->company;
        }
        return $item;
    }
}