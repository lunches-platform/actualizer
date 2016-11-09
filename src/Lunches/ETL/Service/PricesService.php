<?php


namespace Lunches\ETL\Service;

class PricesService extends AbstractService
{
    public function create(\DateTimeImmutable $date, $value, array $items)
    {
        return $this->makeRequest('PUT', '/prices/'.$date->format($this->apiDateFormat), [
            'json' => [
                'value' => $value,
                'items' => $items
            ]
        ]);
    }
}