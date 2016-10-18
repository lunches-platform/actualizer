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
        return array_key_exists($index, $this->weekDays) ? $this->weekDays[$index] : null;
    }
}