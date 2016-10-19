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
    /** @var array */
    private $dishesCache;

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
        $this->loadCache();
        $this->logger->addInfo('Sync dish "'.$name.'"');
        $dish = $this->findFromCache($name);

        if (!$dish) {
            throw new \RuntimeException('Dish not found');
//            $this->logger->addInfo('Dish not found. Start creating...');
//            $dish = $this->dishesService->create($name, $type);
//            $this->logger->addInfo('Dish created');
        }
        $this->logger->addInfo('Single dish found');

        return $dish;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    private function findFromCache($name)
    {
        foreach ($this->dishesCache as $dish) {
            if ($dish['name'] === $name) {
                return $dish;
            }
        }
        return null;
    }

    private function loadCache()
    {
        if (null === $this->dishesCache) {
            $this->dishesCache = $this->dishesService->fetchAll();
        }
    }
}