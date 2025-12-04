<?php

namespace App\Config;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];

    public static function load(): void
    {
        if (empty(self::$config)) {
            $dotenv = Dotenv::createImmutable(__DIR__);
            $dotenv->load();
            self::$config = $_ENV;
        }
        
    }

    public static function get(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }
    
    private static function defineConstant(string $key)
    {
        if (!defined($key) && isset(self::$config[$key])) {
            define($key, self::$config[$key]);
        }
    }
}

// Загружаем конфиг при подключении
Config::load();