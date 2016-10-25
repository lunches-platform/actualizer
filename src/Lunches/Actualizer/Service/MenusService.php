<?php


namespace Lunches\Actualizer\Service;

use GuzzleHttp\Exception\ClientException;
use Lunches\Actualizer\Entity\Menu;

class MenusService extends AbstractService
{
    /**
     * @param \DateTimeImmutable $date
     * @return array
     * @throws \GuzzleHttp\Exception\ClientException
     */
    public function find(\DateTimeImmutable $date)
    {
        try {
            $menus = $this->makeRequest('GET', '/menus/'.$date->format($this->apiDateFormat));
            return array_map([$this, 'fallbackConvertMenu'], $menus);
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
     * @return Menu[]
     */
    public function findBetween(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate)
    {
        $menus = $this->makeRequest('GET', '/menus', [
            'query' => [
                'startDate' => $startDate->format($this->apiDateFormat),
                'endDate' => $endDate->format($this->apiDateFormat),
            ]
        ]);
        $menus = array_map([$this, 'fallbackConvertMenu'], $menus);
        $menus = array_map([$this, 'fromArray'], $menus);

        return $menus;
    }

    public function create(Menu $menu)
    {
        return $this->makeRequest('PUT', '/menus/'.$menu->date()->format($this->apiDateFormat), [
            'json' => [
                'type' => $menu->type(),
                'products' => $menu->dishes(),
            ]
        ]);
    }

    public function exists(Menu $menu)
    {
        $existentMenus = $this->find($menu->date());
        foreach ($existentMenus as $m) {
            if ($menu->type() === $m['type']) {
                return true;
            }
        }
        return false;
    }

    /**
     * "Products" was renamed to "Dishes", but for REST API
     *
     * @param array $menu
     * @return array
     */
    private function fallbackConvertMenu(array $menu)
    {
        $menu['dishes'] = $menu['products'];
        unset($menu['products']);
        return $menu;
    }

    private function fromArray(array $menu)
    {
        $menu['company'] = $this->company;
        return Menu::fromArray($menu);
    }
}