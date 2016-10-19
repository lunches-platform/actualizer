<?php

namespace Lunches\Actualizer\ValueObject;

use Webmozart\Assert\Assert;

class WeekDays
{
    /**
     * @var array
     */
    private $weekDays;

    private $sheetsDateFormat = 'd.m.Y';

    public function __construct($weekStr)
    {
        $dates = explode('-', $weekStr);
        $dates = array_map('trim', $dates);
        if (count($dates) !== 2) {
            throw new \InvalidArgumentException('Invalid week');
        }

        list ($startDate, $endDate) = $dates;
        $startDate = \DateTimeImmutable::createFromFormat($this->sheetsDateFormat, $startDate);
        $endDate   = \DateTimeImmutable::createFromFormat($this->sheetsDateFormat, $endDate);

        Assert::isInstanceOf($startDate, \DateTimeImmutable::class);
        Assert::isInstanceOf($endDate, \DateTimeImmutable::class);

        $weekDays = [];
        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            $weekDays[] = $currentDate;
            $currentDate = $currentDate->add(new \DateInterval('P1D'));
        }

        $this->weekDays = $weekDays;
    }

    public function at($index)
    {
        if (!array_key_exists($index, $this->weekDays)) {
            throw new \InvalidArgumentException('There is no week day at specified index');
        }
        return $this->weekDays[$index];
    }

    public function first()
    {
        return $this->at(0);
    }

    public function last()
    {
        return $this->at(count($this->weekDays) - 1);
    }
}