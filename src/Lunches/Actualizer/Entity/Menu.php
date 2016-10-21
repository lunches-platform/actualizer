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

    private static $types = ['diet', 'regular'];
    private static $dateFormat = 'Y-m-d';

    public function __construct($id, $date, $type, array $dishes)
    {
        Assert::numeric($id);

        $this->id = $id;
        $this->setDate($date);
        $this->setType($type);
        $this->setDishes($dishes);
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
        }

        $this->dishes = $dishes;
    }

}