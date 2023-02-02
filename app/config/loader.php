<?php

use Phalcon\Autoload\Loader;

$loader = new Loader();

$loader->setDirectories(
    [
        APP_PATH . '/controllers/',
        APP_PATH . '/models/',
        APP_PATH . '/services/',
    ]
);   

$loader->setNamespaces(
    [
       'App'             => APP_PATH,
       'App\Controllers' => APP_PATH . '/controllers/',
       'App\Models'      => APP_PATH . '/models/',
       'App\Services'      => APP_PATH . '/services/',
       'App\Helpers'      => APP_PATH . '/helpers/',
    ]
);

$loader->register();