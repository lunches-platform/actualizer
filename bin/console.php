<?php

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

$app = new Silex\Application();

require_once __DIR__ . '/../bootstrap.php';

/** @var Application $console */
$console = $app['console'];
$console->add(new \Lunches\Actualizer\Command\MenusSynchronizerCommand());
$console->add(new \Lunches\Actualizer\Command\OrdersSynchronizerCommand());
$console->add(new \Lunches\Actualizer\Command\ReportsCommand());
$console->run();
