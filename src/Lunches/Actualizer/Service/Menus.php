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
    public function create($menu)
    {
        /** @var \DateTimeImmutable $date */
        $date = $menu['date'];
        $response = $this->client->request('POST', '/menus/'.$date->format($this->apiDateFormat), [
            'json' => $menu,
        ]);
        $body = (string) $response->getBody();

        return (array) json_decode($body, true);
    }
}