<?php


namespace Lunches\Actualizer\Service;


use GuzzleHttp\Client;
use Webmozart\Assert\Assert;

class Dishes
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function fetchAll()
    {
        return $this->makeRequest('GET', '/dishes');
    }

    public function find($name)
    {
        return $this->makeRequest('GET', '/dishes', [
            'query' => ['like' => $name]
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

    private function makeRequest($method, $uri, array $params = [])
    {
        $response = $this->client->request($method, $uri, $params);
        $body = (string) $response->getBody();
        return (array) json_decode($body, true);
    }
}