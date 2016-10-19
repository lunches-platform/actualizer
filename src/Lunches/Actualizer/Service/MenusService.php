<?php


namespace Lunches\Actualizer\Service;

use GuzzleHttp\Exception\ClientException;

class MenusService extends AbstractService
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

    /**
     * @param \DateTimeImmutable $startDate
     * @param \DateTimeImmutable $endDate
     * @return array
     */
    public function findBetween(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        return $this->fallbackConvertMenus($this->makeRequest('GET', '/menus', [
            'query' => [
                'startDate' => $startDate->format($this->apiDateFormat),
                'endDate' => $endDate->format($this->apiDateFormat),
            ]
        ]));
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

    /**
     * "Products" was renamed to "Dishes", but for REST API
     *
     * @param array $menus
     * @return array
     */
    private function fallbackConvertMenus(array $menus)
    {
        return array_map(function($menu) {
            $menu['dishes'] = $menu['products'];
            unset($menu['products']);
            return $menu;
        }, $menus);
    }
}