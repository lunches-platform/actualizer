<?php


namespace Lunches\Actualizer\Entity;


use Webmozart\Assert\Assert;

class LineItem implements \JsonSerializable
{
    /**
     * @var array
     */
    private $dish;
    /**
     * @var string
     */
    private $size;
    /**
     * @var array
     */
    private static $sizes = ['big', 'medium'];

    public function __construct(Order $order, $dish, $size)
    {
        $this->setSize($size);
        $this->setDish($dish);
    }

    public static function fromArray(array $data)
    {
        Assert::keyExists($data, 'dish');
        Assert::keyExists($data, 'size');
        Assert::keyExists($data, 'order');

        return new LineItem(
            $data['order'],
            $data['dish'],
            $data['size']
        );
    }

    public function toArray()
    {
        return [
            'dishId' => $this->dish['id'],
            'size' => $this->size,
        ];
    }

    public function dish()
    {
        return $this->dish;
    }

    public function dishType()
    {
        return $this->dish['type'];
    }

    public function size()
    {
        return $this->size;
    }

    private function setSize($size)
    {
        Assert::string($size);
        Assert::oneOf($size, self::$sizes);

        $this->size = $size;
    }

    private function setDish($dish)
    {
        Assert::isArray($dish);
        Assert::keyExists($dish, 'id');
        Assert::keyExists($dish, 'name');
        Assert::keyExists($dish, 'type');

        $this->dish = $dish;
    }


    public function jsonSerialize()
    {
        return $this->toArray();
    }
}