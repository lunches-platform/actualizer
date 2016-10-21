<?php


namespace Lunches\Actualizer\Service;

class PricesService extends AbstractService
{
    public function create(\DateTimeImmutable $date, $value, array $items)
    {
        return $this->makeRequest('PUT', '/prices/'.$date->format($this->apiDateFormat), [
            'json' => [
                'value' => $value,
                'items' => $this->fallbackConvertItems($items)
            ]
        ]);
    }
    /**
     * "Products" was renamed to "Dishes", but for REST API
     *
     * @param array $items
     * @return array
     */
    private function fallbackConvertItems(array $items)
    {
        return array_map(function($item) {
            $item['productId'] = $item['dishId'];
            unset($item['dishId']);
            return $item;
        }, $items);
    }
}