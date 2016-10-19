<?php


namespace Lunches\Actualizer\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class Menus
{
    /**
     * @var Client
     */
    private $client;
    private $apiDateFormat = 'Y-m-d';

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function find(\DateTimeImmutable $date)
    {
        try {
            $response = $this->client->request('GET', '/menus/'.$date->format($this->apiDateFormat));
            $body = (string) $response->getBody();
            return (array) json_decode($body, true);
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
    public function create(\DateTimeImmutable $date, $type, $menuDishes)
    {
        $response = $this->client->request('PUT', '/menus/'.$date->format($this->apiDateFormat), [
            'json' => [
                'type' => $type,
                'products' => $menuDishes,
            ]
        ]);
        $body = (string) $response->getBody();

        return (array) json_decode($body, true);
    }
}