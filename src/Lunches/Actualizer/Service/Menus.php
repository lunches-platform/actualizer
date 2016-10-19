<?php


namespace Lunches\Actualizer\Service;

use GuzzleHttp\Exception\ClientException;

class Menus extends AbstractService
{
    public function find(\DateTimeImmutable $date)
    {
        try {
            return $this->makeRequest('GET', '/menus/'.$date->format($this->apiDateFormat));
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
    public function create(\DateTimeImmutable $date, $type, $menuDishes)
    {
        return $this->makeRequest('PUT', '/menus/'.$date->format($this->apiDateFormat), [
            'json' => [
                'type' => $type,
                'products' => $menuDishes,
            ]
        ]);
    }
}