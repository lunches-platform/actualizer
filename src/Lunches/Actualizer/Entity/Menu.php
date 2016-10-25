<?php


namespace Lunches\Actualizer\Entity;


use Webmozart\Assert\Assert;

class Menu
{
    /**
     * @var int
     */
    private $id;
    /**
     * @var \DateTimeImmutable
     */
    private $date;
    /**
     * One of 'diet' and 'regular'
     * @var string
     */
    private $type;
    /**
     * @var array of dishes
     */
    private $dishes;
    /**
     * Cache property
     * @var array
     */
    private $dishIds = [];

    private static $types = ['diet', 'regular'];
    private static $dateFormat = 'Y-m-d';

    const DISH_TYPE_MEAT = 'meat';
    const DISH_TYPE_FISH = 'fish';
    const DISH_TYPE_SALAD = 'salad';
    const DISH_TYPE_GARNISH = 'garnish';

    public function __construct($id, $date, $type, array $dishes)
    {
        Assert::numeric($id);

        $this->id = $id;
        $this->setDate($date);
        $this->setType($type);
        $this->setDishes($dishes);
    }

    public function isFull()
    {
        $dishTypes = $this->cookingDishTypes();

        return
            (in_array(self::DISH_TYPE_MEAT, $dishTypes, true) || in_array(self::DISH_TYPE_FISH, $dishTypes, true)) &&
            in_array(self::DISH_TYPE_GARNISH, $dishTypes, true) &&
            in_array(self::DISH_TYPE_SALAD, $dishTypes, true);
    }

    public function resolveMenuType(Order $order)
    {
        if (!$this->isCookingAt($order->date())) {
            throw new \InvalidArgumentException('Provided Menu is not cooking at needed day');
        }

        if ($this->canServeOrder($order)) {
            return $this->type;
        }

        return null;
    }

    public function withoutMeat()
    {
        return $this->newDishes(
            $this->withoutDishType('meat')
        );
    }

    public function withoutSalad()
    {
        return $this->newDishes(
            $this->withoutDishType('salad')
        );
    }

    public function withoutGarnish()
    {
        return $this->newDishes(
            $this->withoutDishType('garnish')
        );
    }

    public function onlyMeat()
    {
        return $this->newDishes(
            $this->onlyDishType('meat')
        );
    }

    public function onlySalad()
    {
        return $this->newDishes(
            $this->onlyDishType('salad')
        );
    }

    public function onlyGarnish()
    {
        return $this->newDishes(
            $this->onlyDishType('garnish')
        );
    }

    public function isCookingAt(\DateTimeImmutable $date)
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        return $this->date == $date;
    }

    /**
     * @param bool $toString
     * @return \DateTimeImmutable|string
     */
    public function date($toString = false)
    {
        if ($toString === true) {
            return $this->date->format(self::$dateFormat);
        }
        return $this->date;
    }

    public function cookingDishTypes()
    {
        return array_unique(array_map(function ($dish) {
            return $dish['type'];
        }, $this->dishes));
    }

    public function dishes()
    {
        return $this->dishes;
    }

    public function type()
    {
        return $this->type;
    }

    public static function fromArray(array $data)
    {
        Assert::keyExists($data, 'id');
        Assert::keyExists($data, 'date');
        Assert::keyExists($data, 'type');
        Assert::keyExists($data, 'dishes');
        Assert::isArray($data['dishes']);

        return new Menu(
            $data['id'],
            $data['date'],
            $data['type'],
            $data['dishes']
        );
    }

    private function withoutDishType($dishType)
    {
        return array_filter($this->dishes, function ($dish) use ($dishType) {
            return $dish['type'] !== $dishType;
        });
    }

    private function onlyDishType($dishType)
    {
        return array_filter($this->dishes, function ($dish) use ($dishType) {
            return $dish['type'] === $dishType;
        });
    }

    private function newDishes(array $dishes)
    {
        return new Menu($this->id, $this->date, $this->type, $dishes);
    }

    private function setDate($date)
    {
        if ($date instanceof \DateTimeImmutable) {
            $this->date = $date;
        } else {
            $this->date = new \DateTimeImmutable($date);
        }
    }

    private function setType($type)
    {
        Assert::string($type);
        $type = mb_strtolower($type);
        Assert::oneOf($type, self::$types);

        $this->type = $type;
    }

    private function setDishes(array $dishes)
    {
        foreach ($dishes as $dish) {
            Assert::keyExists($dish, 'type');
            Assert::keyExists($dish, 'id');
        }

        $this->dishIds = array_map(function ($dish) {
            return $dish['id'];
        }, $dishes);
        $this->dishes = $dishes;
    }

    private function canServeOrder(Order $order)
    {
        return array_reduce($order->lineItems(), function ($isCooking, LineItem $lineItem) {
            return $isCooking && $this->isDishCooking($lineItem->dish());
        }, true);
    }

    private function isDishCooking($dish)
    {
        return in_array($dish['id'], $this->dishIds, true);
    }

}