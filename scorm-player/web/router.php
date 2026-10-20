<?php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

if (preg_match('#^/(js|css)/[\w.-]+\.(js|css)$#', $path) && is_file(__DIR__ . $path)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/index.php';
require __DIR__ . '/index.php';
