<?php
require_once __DIR__ . '/env.php';

define('APP_NAME', (string) envValue('APP_NAME', 'NETKING-ACS'));
define('APP_URL', rtrim((string) envValue('APP_URL', detectAppUrl()), '/'));
define('ASSETS_URL', APP_URL . '/assets');

ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string) envValue('SESSION_LIFETIME', 3600));
ini_set('session.cookie_secure', envValue('SESSION_SECURE', str_starts_with(APP_URL, 'https://')) ? '1' : '0');

$sessionName = envValue('SESSION_NAME', 'GACS_SESSION');
if (is_string($sessionName) && $sessionName !== '') {
    session_name($sessionName);
}

session_start();

date_default_timezone_set((string) envValue('APP_TIMEZONE', 'Asia/Jakarta'));

error_reporting(E_ALL);
ini_set('display_errors', envValue('APP_DEBUG', false) ? '1' : '0');
ini_set('log_errors', '1');
ini_set('memory_limit', (string) envValue('PHP_MEMORY_LIMIT', '768M'));

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../lib/helpers.php';
