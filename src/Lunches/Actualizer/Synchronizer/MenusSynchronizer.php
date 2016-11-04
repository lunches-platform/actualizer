<?php

namespace Lunches\Actualizer\Synchronizer;

use Google_Service_Sheets;
use Lunches\Actualizer\Entity\Menu;
use Lunches\Actualizer\PricesGenerator;
use Lunches\Actualizer\ValueObject\WeekDays;
use Monolog\Logger;
use Webmozart\Assert\Assert;
use Lunches\Actualizer\Service\MenusService;

/**
 * Class MenusSynchronizer
 */
class MenusSynchronizer
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
     * @var PricesGenerator
     */
    private $pricesGenerator;

    /**
     * MenusSynchronizer constructor.
     * @param Google_Service_Sheets $sheetsService
     * @param MenusService $menusService
     * @param DishesSynchronizer $dishesSynchronizer
     * @param PricesGenerator $pricesGenerator
     * @param Logger $logger
     * @internal param Client $apiClient
     */
    public function __construct(
        Google_Service_Sheets $sheetsService,
        MenusService $menusService,
        DishesSynchronizer $dishesSynchronizer,
        PricesGenerator $pricesGenerator,
        Logger $logger
    )
    {
        $this->logger = $logger;
        $this->sheetsService = $sheetsService;
        $this->menusService = $menusService;
        $this->dishesSynchronizer = $dishesSynchronizer;
        $this->pricesGenerator = $pricesGenerator;
    }

    public function sync($spreadsheetId, $sheetRange, array $filters = [])
    {
        $menus = [];
        foreach ($this->readWeeks($spreadsheetId, $sheetRange, $filters) as list ($dateRange, $menuType, $weekMenus)) {

            if (array_key_exists('menuType', $filters) && $filters['menuType'] !== $menuType) {
                continue;
            }

            try {
                $this->syncWeek($dateRange, $menuType, $weekMenus);
            } catch (\Exception $e) {
                $this->logger->addError('Reject sync for the whole week (sheet) due to: '.$e->getMessage());
                continue;
            }
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
                    new Menu(111, $menuDate, $menuType, $this->constructDishes($weekDayMenu), $this->menusService->company())
                );
            } catch (\Exception $e) {
                if ($e instanceof \RuntimeException) {
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

        list ($meatOrFish,,$garnish,,$salad) = $weekDayMenu;

        $dishes = [];
        if (!$meatOrFish || !$garnish) {
            $mainDish = $meatOrFish ?: $garnish;
            $dishes[] = $this->dishesSynchronizer->findOne($mainDish);
        } else {
            $dishes[] = $this->dishesSynchronizer->findOne($meatOrFish);
            $dishes[] = $this->dishesSynchronizer->findOne($garnish);
        }
        $dishes[] = $this->dishesSynchronizer->findOne($salad);

        return $dishes;
    }
    private function syncMenu(Menu $menu)
    {
        if (!$this->menusService->exists($menu)) {
            $this->logger->addInfo('Creating menu ...');
            $this->menusService->create($menu);

            $this->logger->addInfo('Generate menu prices...');
            $this->pricesGenerator->generate($menu);
        } else {
            $this->logger->addInfo('Such menu exists, skip');
        }
    }

    private function isWeekSheetValid(array $weekDay)
    {
        return count($weekDay) === 5;
    }

    private function notHoliday(array $weekDay)
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
     * @param string $sheetRange
     * @param array $filters
     * @return \Generator
     */
    private function readWeeks($spreadsheetId, $sheetRange, array $filters = [])
    {
        $response = $this->sheetsService->spreadsheets->get($spreadsheetId);
        $sheets = (array) $response->getSheets();
        $menuType = $this->determineMenuType($response->getProperties()->getTitle());

        /** @var \Google_Service_Sheets_Sheet[] $sheets */
        foreach ($sheets as $sheet) {

            $sheetTitle = $sheet->getProperties()->getTitle();
            $range = $sheetTitle.'!'.$sheetRange;
            $dateRange = $this->getDateRange($sheetTitle);

            if (array_key_exists('weekRange', $filters) && $filters['weekRange'] !== $dateRange) {
                continue;
            }

            $response = $this->sheetsService->spreadsheets_values->get($spreadsheetId, $range, [
                'majorDimension' => 'COLUMNS',
            ]);

            /** @var array $weekDayMenus */
            $weekDayMenus = $response->getValues();

            yield [ $dateRange , $menuType, $weekDayMenus ];
        }
    }

    private function getDateRange($sheetTitle)
    {
        return trim(str_replace('- diet', '', mb_strtolower($sheetTitle)));
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