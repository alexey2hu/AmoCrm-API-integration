<?php

namespace App\Config;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    /**
     * Загружает конфигурацию из .env файла
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Путь к папке Config
        $configDir = __DIR__;
        
        // Проверяем, есть ли .env в папке Config
        $envPath = $configDir . '/.env';
        
        if (file_exists($envPath)) {
            // .env находится в src/Config/
            $dotenv = Dotenv::createImmutable($configDir);
        } else {
            // Пробуем найти .env в корне проекта (рядом с api.php)
            $rootPath = dirname(__DIR__, 2);
            $envPath = $rootPath . '/.env';
            
            if (file_exists($envPath)) {
                $dotenv = Dotenv::createImmutable($rootPath);
            } else {
                // Последняя попытка - в папке src/
                $srcPath = dirname(__DIR__);
                $envPath = $srcPath . '/.env';
                
                if (file_exists($envPath)) {
                    $dotenv = Dotenv::createImmutable($srcPath);
                } else {
                    throw new \RuntimeException('.env файл не найден. Искали в: ' . 
                        $configDir . ', ' . $rootPath . ', ' . $srcPath);
                }
            }
        }
        
        $dotenv->load();
        
        // Загружаем все переменные окружения
        self::$config = $_ENV;
        
        // Определяем константы для обратной совместимости
        self::defineConstants();
        
        self::$loaded = true;
    }

    /**
     * Получает значение конфигурации
     */
    public static function get(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }

    /**
     * Получает все конфигурации
     */
    public static function all(): array
    {
        return self::$config;
    }

    /**
     * Проверяет существование ключа
     */
    public static function has(string $key): bool
    {
        return isset(self::$config[$key]);
    }

    /**
     * Определяет константы для удобства
     */
    private static function defineConstants(): void
    {
        $constants = [
            'AMOCRM_SUBDOMAIN',
            'AMOCRM_CLIENT_ID', 
            'AMOCRM_CLIENT_SECRET',
            'AMOCRM_AUTH_CODE',
            'AMOCRM_REDIRECT_URI',
            'AMOCRM_PIPELINE_ID',
            'AMOCRM_APPLICATION_STAGE_ID',
            'AMOCRM_WAITING_STAGE_ID',
            'AMOCRM_CLIENT_CONFIRMED_STAGE_ID',
            'AMOCRM_BUDGET_THRESHOLD',
            'AMOCRM_COPY_BUDGET_VALUE'
        ];

        foreach ($constants as $constant) {
            if (!defined($constant) && isset(self::$config[$constant])) {
                define($constant, self::$config[$constant]);
            }
        }
    }

    /**
     * Получает конфигурацию в старом формате (для совместимости)
     */
    public static function getLegacyConfig(): array
    {
        return [
            'sub_domain' => self::get('AMOCRM_SUBDOMAIN', ''),
            'client_id' => self::get('AMOCRM_CLIENT_ID', ''),
            'client_secret' => self::get('AMOCRM_CLIENT_SECRET', ''),
            'code' => self::get('AMOCRM_AUTH_CODE', ''),
            'redirect_url' => self::get('AMOCRM_REDIRECT_URI', ''),
            'pipeline_id' => (int)self::get('AMOCRM_PIPELINE_ID', 0),
            'application_stage_id' => (int)self::get('AMOCRM_APPLICATION_STAGE_ID', 0),
            'waiting_stage_id' => (int)self::get('AMOCRM_WAITING_STAGE_ID', 0),
            'client_confirmed_stage_id' => (int)self::get('AMOCRM_CLIENT_CONFIRMED_STAGE_ID', 0),
            'budget_threshold' => (int)self::get('AMOCRM_BUDGET_THRESHOLD', 5000),
            'copy_budget_value' => (int)self::get('AMOCRM_COPY_BUDGET_VALUE', 4999),
            'debug_mode' => true,
            'limit' => 250
        ];
    }
}