<?php

namespace App\Handlers;

use App\Clients\AmoCrmV4Client;
use App\Config\Config;

abstract class BaseHandler
{
    protected AmoCrmV4Client $amoClient;
    protected array $config;
    protected bool $debugMode = false;
    
    public function __construct()
    {
        // Загружаем конфигурацию
        $this->config = Config::getLegacyConfig();
        
        // Проверяем обязательные поля конфигурации
        $this->validateConfig();
        
        // Создаем клиент для работы с AmoCRM API
        $this->amoClient = new AmoCrmV4Client(
            $this->config['sub_domain'],
            $this->config['client_id'],
            $this->config['client_secret'],
            $this->config['code'],
            $this->config['redirect_url']
        );
        
        // Включаем режим отладки, если указан в конфиге
        $this->debugMode = $this->config['debug_mode'] ?? false;
    }
    
    /**
     * Проверяет обязательные поля конфигурации
     * @throws \RuntimeException если отсутствует обязательное поле
     */
    protected function validateConfig(): void
    {
        $required = ['sub_domain', 'client_id', 'client_secret', 'code', 'redirect_url', 'pipeline_id'];
        
        foreach ($required as $field) {
            if (empty($this->config[$field])) {
                throw new \RuntimeException("Missing required config field: {$field}");
            }
        }
    }
    
    /**
     * Получает параметры обработки из конфига
     * Возвращает настройки для фильтрации и обработки сделок
     */
    protected function getProcessingParameters(): array
    {
        return [
            'pipeline_id' => $this->config['pipeline_id'] ?? 0,
            'application_stage_id' => $this->config['application_stage_id'] ?? 0,
            'client_confirmed_stage_id' => $this->config['client_confirmed_stage_id'] ?? 0,
            'waiting_stage_id' => $this->config['waiting_stage_id'] ?? 0,
            'copy_budget_value' => $this->config['copy_budget_value'] ?? 4999,
            'budget_threshold' => $this->config['budget_threshold'] ?? 5000,
            'limit' => $this->config['limit'] ?? 250,
            'debug_mode' => $this->debugMode
        ];
    }
    
    /**
     * Извлекает бюджет из данных сделки
     * Ищет значение бюджета в основном поле price или кастомных полях
     */
    protected function extractBudgetFromLead(array $lead): float
    {
        // 1. Основное поле 'price'
        if (isset($lead['price']) && is_numeric($lead['price'])) {
            return (float)$lead['price'];
        }
        
        // 2. Поиск в кастомных полях по ключевым словам
        if (isset($lead['custom_fields_values'])) {
            foreach ($lead['custom_fields_values'] as $field) {
                $fieldName = strtolower($field['field_name'] ?? '');
                $fieldCode = strtolower($field['field_code'] ?? '');
                
                $budgetFieldPatterns = [
                    '/бюджет/i',
                    '/стоимость/i', 
                    '/цена/i',
                    '/сумма/i',
                    '/price/i',
                    '/budget/i',
                    '/cost/i',
                    '/amount/i'
                ];
                
                foreach ($budgetFieldPatterns as $pattern) {
                    if (preg_match($pattern, $fieldName) || preg_match($pattern, $fieldCode)) {
                        if (isset($field['values'][0]['value']) && is_numeric($field['values'][0]['value'])) {
                            return (float)$field['values'][0]['value'];
                        }
                    }
                }
            }
        }
        
        return 0.0;
    }
    
    /**
     * Создает структурированный ответ для контроллера
     */
    protected function createResponse(bool $success, string $message, array $data = []): array
    {
        return [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
    }
}