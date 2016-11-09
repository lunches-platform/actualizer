<?php


namespace Lunches\ETL\Entity;


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
     * @var string
     */
    private $company;
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
    const DISH_TYPE_MAIN = 'main';

    public static $dishTypes = [
        self::DISH_TYPE_MAIN,
        self::DISH_TYPE_MEAT,
        self::DISH_TYPE_FISH,
        self::DISH_TYPE_GARNISH,
        self::DISH_TYPE_SALAD,
    ];

    public function __construct($id, $date, $type, array $dishes, $company)
    {
        Assert::numeric($id);
        Assert::string($company);

        $this->id = $id;
        $this->company = $company;
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
        // fix probable invalid user input for combined dishes
        if ($this->hasMainDish() && !$this->hasMeat()) {
            return $this;
        }
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
        // fix probable invalid user input for combined dishes
        if ($this->hasMainDish() && !$this->hasGarnish()) {
            return $this;
        }
        return $this->newDishes(
            $this->onlyDishType('garnish')
        );
    }

    public function isCookingAt(\DateTimeImmutable $date)
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        return $this->date == $date;
    }
    public function isCookingFor($company)
    {
        return $this->company === $company;
    }

    public function isCooking($dish)
    {
        return in_array($dish['id'], $this->dishIds, true);
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

    /**
     * Whether menu type diet or regular
     *
     * @param string $type
     * @return bool
     */
    public function isType($type)
    {
        return $this->type === $type;
    }

    public static function fromArray(array $data)
    {
        Assert::keyExists($data, 'id');
        Assert::keyExists($data, 'date');
        Assert::keyExists($data, 'type');
        Assert::keyExists($data, 'dishes');
        Assert::keyExists($data, 'company');
        Assert::isArray($data['dishes']);

        return new Menu(
            $data['id'],
            $data['date'],
            $data['type'],
            $data['dishes'],
            $data['company']
        );
    }
    private function isFull()
    {
        $dishTypes = $this->cookingDishTypes();

        $isMain = (in_array(self::DISH_TYPE_MEAT, $dishTypes, true) || in_array(self::DISH_TYPE_FISH, $dishTypes, true));
        $isGarnish = in_array(self::DISH_TYPE_GARNISH, $dishTypes, true);
        $isSalad = in_array(self::DISH_TYPE_SALAD, $dishTypes, true);

        if (in_array(self::DISH_TYPE_MAIN, $dishTypes, true)) {
            $isMain = true;
            $isGarnish = true;
        }

        return $isMain && $isGarnish && $isSalad;
    }

    private function withoutDishType($dishType)
    {
        $this->assertFull();
        return array_filter($this->dishes, function ($dish) use ($dishType) {
            return $dish['type'] !== $dishType;
        });
    }

    private function onlyDishType($dishType)
    {
        $this->assertFull();
        return array_filter($this->dishes, function ($dish) use ($dishType) {
            return $dish['type'] === $dishType;
        });
    }

    private function newDishes(array $dishes)
    {
        return new Menu($this->id, $this->date, $this->type, $dishes, $this->company);
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
    private function assertFull()
    {
        if (!$this->isFull()) {
            throw new \InvalidArgumentException('Menu must be full to perform this action');
        }
    }

    private function hasDishType($type)
    {
        Assert::oneOf($type, self::$dishTypes);

        return in_array($type, $this->cookingDishTypes(), true);
    }

    private function hasMeat()
    {
        return $this->hasDishType(self::DISH_TYPE_MEAT);
    }

    private function hasGarnish()
    {
        return $this->hasDishType(self::DISH_TYPE_GARNISH);
    }

    private function hasMainDish()
    {
        return $this->hasDishType(self::DISH_TYPE_MAIN);
    }

}