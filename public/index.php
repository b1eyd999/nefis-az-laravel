<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Bu fayl "public_html/nefis.az"-dədir (addon domain docroot), Laravel
// tətbiqinin əsli isə "/home/darkftga/nefis-laravel"-dədir — iki qat yuxarı.

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../../nefis-laravel/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../../nefis-laravel/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../../nefis-laravel/bootstrap/app.php';

$app->handleRequest(Request::capture());
