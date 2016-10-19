<?php

namespace Lunches\Actualizer\Synchronizer;

use Google_Service_Sheets;
use GuzzleHttp\Exception\ClientException;
use Lunches\Actualizer\ValueObject\WeekDays;
use Monolog\Logger;
use Webmozart\Assert\Assert;
use Lunches\Actualizer\Service\Menus as MenusService;
use Lunches\Actualizer\Synchronizer\Dishes as DishesSynchronizer;

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
     * @var DishesSynchronizer
     */
    private $dishesSynchronizer;

    /**
     * MenusSynchronizer constructor.
     * @param Google_Service_Sheets $sheetsService
     * @param MenusService $menusService
     * @param DishesSynchronizer $dishesSynchronizer
     * @param Logger $logger
     * @internal param Client $apiClient
     */
    public function __construct(
        Google_Service_Sheets $sheetsService,
        MenusService $menusService,
        DishesSynchronizer $dishesSynchronizer,
        Logger $logger
    )
    {
        $this->logger = $logger;
        $this->sheetsService = $sheetsService;
        $this->menusService = $menusService;
        $this->dishesSynchronizer = $dishesSynchronizer;
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
        $this->logger->addInfo('Sync week "'.$dateRange.'"');

        $weekDays = new WeekDays($dateRange);
        $weekMenus = array_filter($weekMenus, [$this, 'isWeekSheetValid']);
        $weekMenus = array_filter($weekMenus, [$this, 'notHoliday']);

        foreach ($weekMenus as $i => $weekDayMenu) {
            $menuDate = $weekDays->at($i);
            $this->logger->addInfo('Sync menu for '.$menuDate->format('Y-m-d'));

            try {
                $this->syncMenu(
                    $menuDate,
                    $menuType,
                    $this->constructMenuDishes($weekDayMenu)
                );
            } catch (\Exception $e) {
                if ($e instanceof ClientException || $e instanceof \RuntimeException) {
                    $this->logger->addError("Can't sync menu due to: ". $e->getMessage());
                    continue;
                }
                throw $e;
            }
        }
    }

    private function constructDishes(array $weekDayMenu)
    {
        Assert::keyExists($weekDayMenu, 0);
        Assert::keyExists($weekDayMenu, 2);
        Assert::keyExists($weekDayMenu, 4);

        $dishes = [];
        $dishes[] = $this->dishesSynchronizer->syncOne($weekDayMenu[0], 'meat');
        $dishes[] = $this->dishesSynchronizer->syncOne($weekDayMenu[2], 'garnish');
        $dishes[] = $this->dishesSynchronizer->syncOne($weekDayMenu[4], 'salad');

        return $dishes;
    }
    private function constructMenuDishes($weekDayMenu)
    {
        $dishes = $this->constructDishes($weekDayMenu);
        $positions = range(1, count($dishes), 1);

        return array_map(function ($dish, $position) {
            return [
                'dish' => $dish,
                'position' => $position,
            ];
        }, $dishes, $positions);
    }
    private function syncMenu(\DateTimeImmutable $date, $menuType, $menuDishes)
    {
        $existentMenu = $this->menusService->find($date);
        if (!$existentMenu) {
            $this->logger->addInfo('Creating menu ...');
            return (bool) $this->menusService->create($date, $menuType, $menuDishes);
        }
        $this->logger->addInfo('Such menu exists, skip');

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