<?php


namespace Lunches\Actualizer;


use Lunches\Actualizer\Service\PricesService;
use Lunches\Actualizer\Entity\Menu;

class PricesGenerator
{
    private static $priceVariants = [
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_MEAT, Menu::DISH_TYPE_GARNISH, Menu::DISH_TYPE_SALAD],
            'value' => 70,
            'title' => 'Большая (мясо)',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_MAIN, Menu::DISH_TYPE_SALAD],
            'value' => 70,
            'title' => 'Большая (салат + жаркое или плов)',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_FISH, Menu::DISH_TYPE_GARNISH, Menu::DISH_TYPE_SALAD],
            'value' => 90,
            'title' => 'Большая (рыба)',
        ],
        [
            'size' => 'medium',
            'contents' => [Menu::DISH_TYPE_MEAT, Menu::DISH_TYPE_GARNISH, Menu::DISH_TYPE_SALAD],
            'value' => 45,
            'title' => 'Средняя (мясо)',
        ],
        [
            'size' => 'medium',
            'contents' => [Menu::DISH_TYPE_MAIN, Menu::DISH_TYPE_SALAD],
            'value' => 45,
            'title' => 'Средняя (салат + жаркое или плов)',
        ],
        [
            'size' => 'medium',
            'contents' => [Menu::DISH_TYPE_FISH, Menu::DISH_TYPE_GARNISH, Menu::DISH_TYPE_SALAD],
            'value' => 55,
            'title' => 'Средняя (рыба)',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_MEAT],
            'value' => 35,
            'title' => 'Только мясо',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_FISH],
            'value' => 55,
            'title' => 'Только рыба',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_SALAD],
            'value' => 25,
            'title' => 'Только салат',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_GARNISH],
            'value' => 30,
            'title' => 'Только гарнир',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_GARNISH, Menu::DISH_TYPE_SALAD],
            'value' => 45,
            'title' => 'Большая без мяса (или рыбы)',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_MEAT, Menu::DISH_TYPE_GARNISH],
            'value' => 55,
            'title' => 'Большая без салата (мясо)',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_FISH, Menu::DISH_TYPE_GARNISH],
            'value' => 75,
            'title' => 'Большая без салата (рыба)',
        ],
        [
            'size' => 'big',
            'contents' => ['main'],
            'value' => 55,
            'title' => 'Большая без салата (жаркое или плов)',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_MEAT, Menu::DISH_TYPE_SALAD],
            'value' => 55,
            'title' => 'Большая без гарнира (мясо)',
        ],
        [
            'size' => 'big',
            'contents' => [Menu::DISH_TYPE_FISH, Menu::DISH_TYPE_SALAD],
            'value' => 75,
            'title' => 'Большая без гарнира (рыба)',
        ],
        [
            'size' => 'medium',
            'contents' => [Menu::DISH_TYPE_GARNISH, Menu::DISH_TYPE_SALAD],
            'value' => 35,
            'title' => 'Средняя без мяса (или рыбы)',
        ],
        [
            'size' => 'medium',
            'contents' => [Menu::DISH_TYPE_MEAT, Menu::DISH_TYPE_GARNISH],
            'value' => 40,
            'title' => 'Средняя без салата (мясо)',
        ],
        [
            'size' => 'medium',
            'contents' => ['main'],
            'value' => 40,
            'title' => 'Средняя без салата (жаркое или плов)',
        ],
        [
            'size' => 'medium',
            'contents' => [Menu::DISH_TYPE_FISH, Menu::DISH_TYPE_GARNISH],
            'value' => 50,
            'title' => 'Средняя без салата (рыба)',
        ],
        [
            'size' => 'medium',
            'contents' => [Menu::DISH_TYPE_MEAT, Menu::DISH_TYPE_SALAD],
            'value' => 40,
            'title' => 'Средняя без гарнира (мясо)',
        ],
        [
            'size' => 'medium',
            'contents' => [Menu::DISH_TYPE_FISH, Menu::DISH_TYPE_SALAD],
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
        $priceVariants = array_filter(self::$priceVariants, $this->onlyMenuDishes($menu));

        foreach ($priceVariants as $variant) {
            $items = $this->createItems($menu, $variant['size'], $variant['contents']);
            $this->pricesService->create($menu->date(), $variant['value'], $items);
        }
    }

    private function onlyMenuDishes(Menu $menu)
    {
        return function ($variant) use ($menu) {
            return count(array_diff($variant['contents'], $menu->cookingDishTypes())) === 0;
        };
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

        return $items;
    }
}