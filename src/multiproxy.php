<?php

use application\controllers\ParseController;

ini_set('display_errors', '1');

DEFINE('DS', DIRECTORY_SEPARATOR);

require_once __DIR__ . DS . 'loader.php';

try {
    $controller = new ParseController();
    $controller->multiThreadGetProxy();
} catch (Exception $e) {
    echo 'Thrown exception: ', $e->getMessage(), "\n";
}
