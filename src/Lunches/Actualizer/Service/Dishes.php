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

    public function find($name)
    {
        $response = $this->client->request('GET', '/dishes', [
            'query' => ['like' => $name]
        ]);
        $body = (string) $response->getBody();
        return (array) json_decode($body, true);
    }
    public function create($name, $type)
    {
        Assert::string($name);
        Assert::string($type);

        $response = $this->client->request('POST', '/dishes', [
            'json' => [
                'name' => $name,
                'type' => $type,
            ]
        ]);
        $body = (string) $response->getBody();

        return (array) json_decode($body, true);
    }
}