<?php


namespace Lunches\Actualizer\Service;

use GuzzleHttp\Exception\ClientException;

class Menus extends AbstractService
{
    /**
     * @param \DateTimeImmutable $date
     * @return array
     */
    public function find(\DateTimeImmutable $date)
    {
        try {
            return $this->makeRequest('GET', '/menus/'.$date->format($this->apiDateFormat));
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                return [];
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

    public function exists(\DateTimeImmutable $date, $type)
    {
        $existentMenus = $this->find($date);
        foreach ($existentMenus as $menu) {
            if ($type === $menu['type']) {
                return true;
            }
        }
        return false;
    }
}