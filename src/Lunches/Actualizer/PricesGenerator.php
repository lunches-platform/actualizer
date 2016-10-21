<?php


namespace Lunches\Actualizer;


use Lunches\Actualizer\Service\PricesService;
use Lunches\Actualizer\Entity\Menu;

class PricesGenerator
{
    private static $priceVariants = [
        [
            'size' => 'big',
            'contents' => ['meat', 'garnish', 'salad'],
            'value' => 70,
            'title' => 'Большая (мясо)',
        ],
        [
            'size' => 'big',
            'contents' => ['fish', 'garnish', 'salad'],
            'value' => 90,
            'title' => 'Большая (рыба)',
        ],
        [
            'size' => 'medium',
            'contents' => ['meat', 'garnish', 'salad'],
            'value' => 45,
            'title' => 'Средняя (мясо)',
        ],
        [
            'size' => 'medium',
            'contents' => ['fish', 'garnish', 'salad'],
            'value' => 55,
            'title' => 'Средняя (рыба)',
        ],
        [
            'size' => 'big',
            'contents' => ['meat'],
            'value' => 35,
            'title' => 'Только мясо',
        ],
        [
            'size' => 'big',
            'contents' => ['fish'],
            'value' => 55,
            'title' => 'Только рыба',
        ],
        [
            'size' => 'big',
            'contents' => ['salad'],
            'value' => 25,
            'title' => 'Только салат',
        ],
        [
            'size' => 'big',
            'contents' => ['garnish'],
            'value' => 30,
            'title' => 'Только гарнир',
        ],
        [
            'size' => 'big',
            'contents' => ['garnish', 'salad'],
            'value' => 45,
            'title' => 'Большая без мяса (или рыбы)',
        ],
        [
            'size' => 'big',
            'contents' => ['meat', 'garnish'],
            'value' => 55,
            'title' => 'Большая без салата (мясо)',
        ],
        [
            'size' => 'big',
            'contents' => ['fish', 'garnish'],
            'value' => 75,
            'title' => 'Большая без салата (рыба)',
        ],
        [
            'size' => 'big',
            'contents' => ['meat', 'salad'],
            'value' => 55,
            'title' => 'Большая без гарнира (мясо)',
        ],
        [
            'size' => 'big',
            'contents' => ['fish', 'salad'],
            'value' => 75,
            'title' => 'Большая без гарнира (рыба)',
        ],
        [
            'size' => 'medium',
            'contents' => ['garnish', 'salad'],
            'value' => 35,
            'title' => 'Средняя без мяса (или рыбы)',
        ],
        [
            'size' => 'medium',
            'contents' => ['meat', 'garnish'],
            'value' => 40,
            'title' => 'Средняя без салата (мясо)',
        ],
        [
            'size' => 'medium',
            'contents' => ['fish', 'garnish'],
            'value' => 50,
            'title' => 'Средняя без салата (рыба)',
        ],
        [
            'size' => 'medium',
            'contents' => ['meat', 'salad'],
            'value' => 40,
            'title' => 'Средняя без гарнира (мясо)',
        ],
        [
            'size' => 'medium',
            'contents' => ['fish', 'salad'],
            'value' => 50,
            'title' => 'Средняя без гарнира (рыба)',
        ],
    ];
    /** @var PricesService  */
    private $pricesService;

    public function __construct(PricesService $pricesService)
    {
        $this->pricesService = $pricesService;
    }
    public function generate(Menu $menu)
    {
        foreach (self::$priceVariants as $variant) {
            $items = $this->createItems($menu, $variant['size'], $variant['contents']);
            $this->pricesService->create($menu->date(), $variant['value'], $items);
        }
    }

    private function createItems(Menu $menu, $size, array $contents)
    {
        $dishes = $menu->dishes();
        $dishes = array_filter($dishes, function ($dish) use ($contents) {
            return in_array($dish['type'], $contents, true);
        });
        $items = array_map(function ($dish) use ($size) {
            return [
                'dishId' => $dish['id'],
                'size' => $size
            ];
        }, $dishes);

        return [
            'items' => $items,
        ];
    }
}