<?php


namespace Lunches\Actualizer\Entity;


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
    private $userId;
    /**
     * @var string
     */
    private $address;
    /**
     * @var array
     */
    private $lineItems;

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

    public function __construct($date, $userId, $address)
    {
        $this->setShipmentDate($date);
        $this->setUserId($userId);
        $this->setAddress($address);
        $this->lineItems = [];
    }

    public function setItemsFromOrderString(Menu $menu, $orderStr)
    {
        Assert::oneOf($orderStr, self::$orderVariants);

        if (!$menu->isCookingAt($this->shipmentDate)) {
            throw new \InvalidArgumentException('Provided Menu is not cooking at needed day');
        }

        if (!$menu->isFull()) {
            throw new \InvalidArgumentException('To create LineItems Menu must be full');
        }

        $size = $this->sizeFromOrderString($orderStr);
        $dishes = $this->dishesFromOrderString($orderStr, $menu);

        array_walk($dishes, function ($dish) use ($size) {
            $this->addLineItem($dish['id'], $size);
        });
    }

    public function addLineItem($dishId, $size)
    {
        $this->lineItems[] = [
            'dishId' => $dishId,
            'size' => $size,
        ];
    }

    public function clear()
    {
        return $this->lineItems = [];
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

    public function userId()
    {
        return $this->userId;
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
        // by default
        return self::SIZE_MEDIUM;
    }

    private function setShipmentDate($date)
    {
        if ($date instanceof \DateTimeImmutable) {
            $this->shipmentDate = $date;
        } else {
            $this->shipmentDate = new \DateTimeImmutable($date);
        }
    }

    private function setUserId($userId)
    {
        // TODO
//        Assert::uuid($userId);

        $this->userId = $userId;
    }

    private function setAddress($address)
    {
        Assert::string($address);
        Assert::range(mb_strlen($address), 1, 150, 'address must be greater than zero and less than 150 characters');

        $this->address = $address;
    }

    public function jsonSerialize()
    {
        return [
            'userId' => $this->userId,
            'shipmentDate' => $this->shipmentDate,
            'address' => $this->address,
            'items' => $this->lineItems,
        ];
    }
}