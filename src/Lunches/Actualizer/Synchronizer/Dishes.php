<?php

namespace Lunches\Actualizer\Synchronizer;

use Monolog\Logger;
use Lunches\Actualizer\Service\Dishes as DishesService;

/**
 * Class Dishes
 */
class Dishes
{
    /** @var Logger */
    private $logger;
    /** @var  DishesService */
    private $dishesService;

    /**
     * Dishes constructor.
     *
     * @param DishesService $dishesService
     * @param Logger $logger
     */
    public function __construct(DishesService $dishesService, Logger $logger)
    {
        $this->logger = $logger;
        $this->dishesService = $dishesService;
    }

    public function syncOne($name, $type)
    {
        $this->logger->addInfo('Sync dish "'.$name.'"');
        $dishes = $this->dishesService->find($name);

        $cnt = count($dishes);
        if ($cnt === 0) {
            $this->logger->addInfo('Dish not found. Start creating...');
            $dish = $this->dishesService->create($name, $type);
            $this->logger->addInfo('Dish created');
        } elseif ($cnt === 1) {
            $this->logger->addInfo('Single dish found');
            $dish = array_shift($dishes);
        } else {
            // TODO more robust error handling
            throw new \RuntimeException('Several dishes found');
        }

        return $dish;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }
}