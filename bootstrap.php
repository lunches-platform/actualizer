<?php


use GuzzleHttp\Client;
use Silex\Application;

require_once __DIR__.'/vendor/autoload.php';

$app = new \Silex\Application();

$app['debug'] = true;
$app['root_dir'] = __DIR__ . '/';
/** @var array $parameters */
$parameters = require __DIR__.'/parameters.php';
foreach ($parameters as $configKey => $configValue) {
    /** @var $configKey string */
    $app[$configKey] = $configValue;
}

$app->register(new \Silex\Provider\MonologServiceProvider());
$app->register(new Knp\Provider\ConsoleServiceProvider(), [
    'console.name'              => 'Lunches Actualizer',
    'console.version'           => '0.1.0',
    'console.project_directory' => __DIR__.'/..'
]);

$app['guzzle:lunches-api'] = function(Application $app) {
    return new Client([ 'base_uri' => $app['lunches-api'] ]);
};

return $app;
