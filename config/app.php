<?php

define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_NAME',    'REMOVED_FOR_SECURITY');
define('DB_USER',    'REMOVED_FOR_SECURITY');
define('DB_PASS',    'REMOVED_FOR_SECURITY');
define('DB_CHARSET', 'REMOVED_FOR_SECURITY');
define('ENV', 'local');

$base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
define('BASE_URL', ($base === '/' ? '' : $base));

date_default_timezone_set('REMOVED_FOR_SECURITY');

if (ENV === 'local') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}