<?php

namespace application\core;

class Connect
{
    public static $db;

    static function execute()
    {
        $config = __DIR__ . DS . '../../config/config.php';
        self::$db = DB::instance($config);
    }
}