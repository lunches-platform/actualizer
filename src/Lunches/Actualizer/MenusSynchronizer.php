<?php

namespace Lunches\Actualizer;

use Google_Service_Sheets;
use Monolog\Logger;
use GuzzleHttp\Client;

/**
 * Class MenusSynchronizer.
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
     * MenusSynchronizer constructor.
     * @param Google_Service_Sheets $sheetsService
     * @param Client $apiClient
     * @param Logger $logger
     */
    public function __construct(Google_Service_Sheets $sheetsService, Client $apiClient, Logger $logger)
    {
        $this->logger = $logger;
        $this->sheetsService = $sheetsService;
        $this->apiClient = $apiClient;
    }

    public function sync($spreadsheetId)
    {
        $menus = [];
        foreach ($this->readWeeks($spreadsheetId) as $week) {
            /** @var array $week */
            foreach ($week as $weekDayMenu) {
                if (count($weekDayMenu) !== 5) {
                    throw new \InvalidArgumentException('Invalid week menu');
                }
                array_map('trim', $weekDayMenu);
            }
        }

        return $menus;
    }

    /**
     * @param string $spreadsheetId
     * @return \Generator
     */
    private function readWeeks($spreadsheetId)
    {
        $response = $this->sheetsService->spreadsheets->get($spreadsheetId);
        $sheets = $response->getSheets();

        if (!count($sheets)) {
            yield;
        }

        // just read the first currently
        $sheet = array_shift($sheets);
        $week = $sheet->getProperties()->getTitle();
        $range = $week.'!B4:F8';
        $response = $this->sheetsService->spreadsheets_values->get($spreadsheetId, $range, [
            'majorDimension' => 'COLUMNS',
        ]);

        /** @var array $weekDayMenus */
        $weekDayMenus = $response->getValues();

        yield $weekDayMenus;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
}