<?php

namespace application\core;

class DB
{
    protected static $instance = null;

    final private function __construct()
    {
    }

    final private function __clone()
    {
    }

    static function config($file)
    {
        return include($file);
    }

    public static function instance($config)
    {
        if (self::$instance === null) {
            $opt = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => true
            ];

            $conf =  include($config);

            $dsn = 'mysql:host=' . $conf['db_host'] . ';dbname=' . $conf['db_name'] . ';charset=utf8';
            self::$instance = new \PDO($dsn, $conf['db_user'], $conf['db_password'], $opt);
        }
        return self::$instance;
    }
}
