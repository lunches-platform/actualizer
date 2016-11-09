<?php


namespace Lunches\ETL\Entity;


use Lunches\ETL\ValueObject\Address;
use Webmozart\Assert\Assert;

class Order implements \JsonSerializable
{
    /**
     * @var \DateTimeImmutable
     */
    private $shipmentDate;
    /**
     * @var string
     */
    private $user;
    /**
     * @var Address
     */
    private $address;
    /**
     * @var LineItem[]
     */
    private $lineItems;

    /** @var int */
    private $quantity;
    /** @var int  */
    private $defaultQuantity = 0;
    private static $dateFormat = 'Y-m-d';

    const BIG = 'Большая';
    const BIG_NO_MEAT = 'Большая без мяса';
    const BIG_NO_SALAD = 'Большая без салата';
    const BIG_NO_GARNISH= 'Большая без гарнира';
    const MEDIUM = 'Средняя';
    const MEDIUM_NO_MEAT = 'Средняя без мяса';
    const MEDIUM_NO_SALAD = 'Средняя без салата';
    const MEDIUM_NO_GARNISH= 'Средняя без гарнира';
    const ONLY_MEAT = 'Только мясо';
    const ONLY_SALAD = 'Только салат';
    const ONLY_GARNISH = 'Только гарнир';

    const SIZE_BIG = 'big';
    const SIZE_MEDIUM = 'medium';

    private static $orderVariants = [
        self::BIG,
        self::BIG_NO_MEAT,
        self::BIG_NO_SALAD,
        self::BIG_NO_GARNISH,
        self::MEDIUM,
        self::MEDIUM_NO_MEAT,
        self::MEDIUM_NO_SALAD,
        self::MEDIUM_NO_GARNISH,
        self::ONLY_MEAT,
        self::ONLY_SALAD,
        self::ONLY_GARNISH,
    ];

    public function __construct($date, $user, Address $address)
    {
        $this->setShipmentDate($date);
        $this->setUser($user);
        $this->address = $address;
        $this->quantity = $this->defaultQuantity;
        $this->lineItems = [];
    }

    public function setItemsFromOrderString(Menu $menu, $orderStr)
    {
        Assert::oneOf($orderStr, self::$orderVariants);

        if (!$menu->isCookingAt($this->shipmentDate)) {
            throw new \InvalidArgumentException('Provided Menu is not cooking at needed day');
        }

        $size = $this->sizeFromOrderString($orderStr);
        $dishes = $this->dishesFromOrderString($orderStr, $menu);

        array_walk($dishes, function ($dish) use ($size) {
            $this->addLineItem($dish, $size);
        });
    }

    public function addLineItem($dish, $size)
    {
        $this->lineItems[] = new LineItem($this, $dish, $size);
    }

    public function toDisplayString()
    {
        $dishTypes = $this->orderedDishTypes();
        $dishTypes = array_map([$this, 'translate'], $dishTypes);
        $dishTypes = implode(', ', $dishTypes);

        return $this->translate($this->getSize()).' - '.$dishTypes;
    }

    /**
     * @param Menu[] $menus
     * @return array|Menu[]
     */
    public function compatibleMenus($menus)
    {
        $menus = array_filter($menus, [$this, 'isCookingAt']);
        $menus = array_filter($menus, [$this, 'isCookingFor']);
        $menus = array_filter($menus, [$this, 'isCookingDishes']);

        return $menus;
    }

    public function resolveMenuType($menus)
    {
        $menus = $this->compatibleMenus($menus);

        $menuTypes = array_map([$this, 'getMenuType'], $menus);
        $menuTypes = array_filter($menuTypes);
        $menuTypes = array_map([$this, 'translate'], $menuTypes);

        if (!count($menuTypes)) {
            throw new \RuntimeException('Can not resolve menu type');
        }

        return array_shift($menuTypes);
    }
    public function clear()
    {
        return $this->lineItems = [];
    }

    public function addQuantity($num = 1)
    {
        Assert::integer($num);
        Assert::greaterThanEq($num, 0, 'Quantity must be greater than zero');

        $this->quantity += $num;
    }

    public function resetQuantity()
    {
        $this->quantity = $this->defaultQuantity;
    }

    public function quantity()
    {
        return $this->quantity;
    }

    /**
     * @param bool $toString
     * @return \DateTimeImmutable|string
     */
    public function date($toString = false)
    {
        if ($toString === true) {
            return $this->shipmentDate->format(self::$dateFormat);
        }
        return $this->shipmentDate;
    }

    public function lineItems()
    {
        return $this->lineItems;
    }

    public static function fromArray(array $data)
    {
        Assert::keyExists($data, 'shipmentDate');
        Assert::keyExists($data, 'user');
        Assert::keyExists($data, 'company');
        Assert::keyExists($data, 'address');
        Assert::keyExists($data, 'items');

        Assert::isArray($data['items']);

        $order = new Order(
            $data['shipmentDate'],
            $data['user'],
            new Address($data['user'], 'Kiev', $data['address'], $data['company'])
        );
        /** @var array $items */
        $items = $data['items'];
        foreach ($items as $item) {
            Assert::keyExists($item, 'size');
            Assert::keyExists($item, 'product');
            $order->addLineItem($item['product'], $item['size']);
        }

        return $order;
    }

    /**
     * @return Address
     */
    public function address()
    {
        return $this->address;
    }

    public function user()
    {
        return $this->user;
    }

    private function dishesFromOrderString($orderStr, Menu $menu)
    {
        switch ($orderStr) {
            case self::BIG:
            case self::MEDIUM:
                $orderedMenu = $menu;
                break;
            case self::BIG_NO_MEAT:
            case self::MEDIUM_NO_MEAT:
                $orderedMenu = $menu->withoutMeat();
                break;
            case self::BIG_NO_SALAD:
            case self::MEDIUM_NO_SALAD:
                $orderedMenu = $menu->withoutSalad();
                break;
            case self::BIG_NO_GARNISH:
            case self::MEDIUM_NO_GARNISH:
                $orderedMenu = $menu->withoutGarnish();
                break;
            case self::ONLY_MEAT:
                $orderedMenu = $menu->onlyMeat();
                break;
            case self::ONLY_GARNISH:
                $orderedMenu = $menu->onlyGarnish();
                break;
            case self::ONLY_SALAD:
                $orderedMenu = $menu->onlySalad();
                break;
            default:
                return [];
        }

        return $orderedMenu->dishes();
    }
    private function sizeFromOrderString($orderString)
    {
        if (in_array($orderString, [ self::BIG, self::BIG_NO_MEAT, self::BIG_NO_SALAD, self::BIG_NO_GARNISH ], true)) {
            return self::SIZE_BIG;
        }
        if (in_array($orderString, [ self::MEDIUM, self::MEDIUM_NO_MEAT, self::MEDIUM_NO_SALAD, self::MEDIUM_NO_GARNISH ], true)) {
            return self::SIZE_MEDIUM;
        }
        if (in_array($orderString, [ self::ONLY_MEAT, self::ONLY_GARNISH, self::ONLY_SALAD ], true)) {
            return self::SIZE_BIG;
        }
        // by default
        return self::SIZE_MEDIUM;
    }

    private function isCookingAt(Menu $menu)
    {
        return $menu->isCookingAt($this->date());
    }

    private function isCookingFor(Menu $menu)
    {
        return $menu->isCookingFor($this->address()->company());
    }

    private function isCookingDishes(Menu $menu)
    {
        return array_reduce($this->lineItems(), function ($isPreviousCooking, LineItem $lineItem) use ($menu) {
            return $isPreviousCooking && $menu->isCooking($lineItem->dish());
        }, true);
    }

    private function setShipmentDate($date)
    {
        if ($date instanceof \DateTimeImmutable) {
            $this->shipmentDate = $date;
        } else {
            $this->shipmentDate = new \DateTimeImmutable($date);
        }
    }

    private function setUser($user)
    {
        Assert::isArray($user);
        Assert::keyExists($user, 'id');
        Assert::keyExists($user, 'fullname');
        Assert::keyExists($user, 'address');
        Assert::uuid($user['id']);

        $this->user = $user;
    }

    public function toArray()
    {
        return [
            'user' => $this->user,
            'shipmentDate' => $this->date(true),
            'address' => $this->address,
            'items' => $this->lineItems,
        ];
    }

    public function jsonSerialize()
    {
        return $this->toArray();
    }

    private function orderedDishTypes()
    {
        return array_map(function(LineItem $lineItem) {
            return $lineItem->dishType();
        }, $this->lineItems);
    }

    private function getSize()
    {
        $sizes = [];
        foreach ($this->lineItems as $lineItem) {
            $sizes[] = $lineItem->size();
        }
        $sizes = array_unique($sizes);
        if (count($sizes) > 1) {
            throw new \RuntimeException('There are more than one Size found per order');
        }
        return array_shift($sizes);
    }

    private function getMenuType(Menu $menu)
    {
        return $menu->type();
    }

    private function translate($value)
    {
        $map = [
            'medium' => 'Средняя',
            'big' => 'Большая',

            'meat' => 'Мясо',
            'garnish' => 'Гарнир',
            'salad' => 'Салат',
            'fish' => 'Рыба',

            'regular' => 'Обычное меню',
            'diet' => 'Диетическое меню',
        ];

        return array_key_exists($value, $map) ? $map[$value] : $value;
    }
}