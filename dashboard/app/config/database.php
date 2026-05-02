<?php
require_once __DIR__ . '/env.php';

define('DB_HOST', (string) envValue('DB_HOST', 'localhost'));
define('DB_USER', (string) envValue('DB_USER', 'gacs-dev'));
define('DB_PASS', (string) envValue('DB_PASSWORD', ''));
define('DB_NAME', (string) envValue('DB_NAME', 'gacs-dev'));

function getDBConnection() {
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            die('Connection failed: ' . $conn->connect_error);
        }

        $conn->set_charset('utf8mb4');
    }

    return $conn;
}
