<?php
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
$applicationRoot=dirname(__DIR__).'/business-suite';
if(file_exists($maintenance=$applicationRoot.'/storage/framework/maintenance.php'))require $maintenance;
require $applicationRoot.'/vendor/autoload.php';
(require_once $applicationRoot.'/bootstrap/app.php')->handleRequest(Request::capture());
