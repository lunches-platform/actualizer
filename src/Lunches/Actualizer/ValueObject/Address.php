<?php

namespace Lunches\Actualizer\ValueObject;

use Webmozart\Assert\Assert;

class Address
{
    /**
     * @var array
     */
    private $user;
    /**
     * @var string
     */
    private $company;
    /**
     * @var string
     */
    private $city;
    /**
     * @var string
     */
    private $street;
    public function __construct($user, $city, $street, $company)
    {
        Assert::isArray($user);
        Assert::string($city);
        Assert::string($street);
        Assert::string($company);
        Assert::range(mb_strlen($street), 1, 150, 'address must be greater than zero and less than 150 characters');
        Assert::range(mb_strlen($company), 1, 150, 'company must be greater than zero and less than 150 characters');

        $this->user = $user;
        $this->city = $city;
        $this->street = $street;
        $this->company = $company;
    }

    public function toString()
    {
        return implode(', ', [$this->city, $this->company, $this->street]);
    }

    /**
     * @return array
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * @return string
     */
    public function company()
    {
        return $this->company;
    }
    /**
     * @return string
     */
    public function city()
    {
        return $this->city;
    }
    /**
     * @return string
     */
    public function street()
    {
        return $this->street;
    }
}
