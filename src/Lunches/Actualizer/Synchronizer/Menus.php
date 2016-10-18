<?php

namespace Lunches\Actualizer\Synchronizer;

use Google_Service_Sheets;
use Lunches\Actualizer\ValueObject\WeekDays;
use Monolog\Logger;
use Webmozart\Assert\Assert;
use Lunches\Actualizer\Service\Menus as MenusService;

/**
 * Class Menus
 */
class Menus
{
    /** @var Logger */
    private $logger;
    /**
     * @var Google_Service_Sheets
     */
    private $sheetsService;

    /**
     * @var MenusService
     */
    private $menusService;

    /**
     * MenusSynchronizer constructor.
     * @param Google_Service_Sheets $sheetsService
     * @param MenusService $menusService
     * @param Logger $logger
     * @internal param Client $apiClient
     */
    public function __construct(Google_Service_Sheets $sheetsService, MenusService $menusService, Logger $logger)
    {
        $this->logger = $logger;
        $this->sheetsService = $sheetsService;
        $this->menusService = $menusService;
    }

    public function sync($spreadsheetId)
    {
        $menus = [];
        foreach ($this->readWeeks($spreadsheetId) as list($dateRange, $menuType, $weekMenus)) {
            $this->syncWeek($dateRange, $menuType, $weekMenus);
        }

        return $menus;
    }
    private function syncWeek($dateRange, $menuType, $weekMenus)
    {
        $weekDays = new WeekDays($dateRange);
        $weekMenus = array_filter($weekMenus, [$this, 'isWeekSheetValid']);
        $weekMenus = array_filter($weekMenus, [$this, 'notHoliday']);

        foreach ($weekMenus as $i => $weekDayMenu) {

            $dishes = $this->constructDishes($weekDayMenu);
            $menu = $this->constructMenu($weekDays->at($i), $menuType, $dishes);

            $this->syncMenu($menu);
        }
    }

    private function constructDishes(array $weekDayMenu)
    {
        Assert::keyExists($weekDayMenu, 0);
        Assert::keyExists($weekDayMenu, 2);
        Assert::keyExists($weekDayMenu, 4);

        return [
            [
                'name' => $weekDayMenu[0],
                'type' => 'meat',
            ], [
                'name' => $weekDayMenu[2],
                'type' => 'garnish',
            ], [
                'name' => $weekDayMenu[4],
                'type' => 'salad',
            ],
        ];
    }
    private function constructMenu($date, $type, $dishes)
    {
        return [
            'date' => $date,
            'type' => $type,
            'menuDishes' => array_map(function ($dish, $position) {
                return [
                    'dish' => $dish,
                    'position' => $position,
                ];
            }, $dishes, range(1, count($dishes), 1)),
        ];
    }
    private function syncMenu(array $menu)
    {
        $existentMenu = $this->menusService->find($menu['date']);
        if (!$existentMenu) {
            return (bool) $this->menusService->create($menu);
        }

        return false;
    }

    protected function isWeekSheetValid(array $weekDay)
    {
        return count($weekDay) === 5;
    }

    protected function notHoliday(array $weekDay)
    {
        foreach ($weekDay as $item) {
            if (mb_strtolower($item) === 'holiday') {
                return false;
            }
        }
        return true;
    }


    /**
     * @param string $spreadsheetId
     * @return \Generator
     */
    private function readWeeks($spreadsheetId)
    {
        $response = $this->sheetsService->spreadsheets->get($spreadsheetId);
        $sheets = $response->getSheets();
        $menuType = $this->determineMenuType($response->getProperties()->getTitle());

        if (!count($sheets)) {
            yield;
        }

        // just read the first currently
        $sheet = array_shift($sheets);
        $weekDateRange = $sheet->getProperties()->getTitle();
        $range = $weekDateRange.'!B4:F8';
        $response = $this->sheetsService->spreadsheets_values->get($spreadsheetId, $range, [
            'majorDimension' => 'COLUMNS',
        ]);

        /** @var array $weekDayMenus */
        $weekDayMenus = $response->getValues();

        yield [ $weekDateRange, $menuType, $weekDayMenus ];
    }

    private function determineMenuType($sheetTitle)
    {
        return mb_stripos($sheetTitle, 'diet') ? 'diet' : 'regular';
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
}