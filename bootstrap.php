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
$app['google:client'] = function (Application $app) {
    $client = new Google_Client();
    $client->setAuthConfig($app['root_dir'].$app['google:auth:service-account-json']);
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS_READONLY]);
    return $client;
};
$app['google:sheets-service'] = function (Application $app) {
    return new Google_Service_Sheets($app['google:client']);
};
$app['service:menus'] = function (Application $app) {
    return new \Lunches\Actualizer\Service\Menus($app['guzzle:lunches-api']);
};
$app['service:dishes'] = function (Application $app) {
    return new \Lunches\Actualizer\Service\Dishes($app['guzzle:lunches-api']);
};
$app['synchronizer:menus'] = function(Application $app) {
    return new \Lunches\Actualizer\Synchronizer\Menus(
        $app['google:sheets-service'],
        $app['service:menus'],
        $app['synchronizer:dishes'],
        $app['logger']
    );
};
$app['synchronizer:dishes'] = function(Application $app) {
    return new \Lunches\Actualizer\Synchronizer\Dishes(
        $app['service:dishes'],
        $app['logger']
    );
};

return $app;
