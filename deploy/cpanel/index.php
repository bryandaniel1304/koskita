<?php

// index.php khusus hosting cPanel -- disalin deploy.sh ke document root
// (~/public_html/koskita/backend/public). Kode KosKita disimpan di luar
// public_html supaya .env, vendor, dan storage tidak bisa diakses dari
// internet; folder document root hanya berisi file publik. __APP_PATH__
// diganti deploy.sh dengan lokasi backend sebenarnya.

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$base = '__APP_PATH__';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $base.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
