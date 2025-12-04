<?php
// src/Handlers/MoveLeadsHandler.php - ИСПРАВЛЕННАЯ ВЕРСИЯ
namespace App\Handlers;

use App\Clients\AmoCrmV4Client;

class MoveLeadsHandler {
    private $amoClient;
    private $config;
    
    public function __construct() {
        // Загружаем конфигурацию
        $this->config = require __DIR__ . '/../Config/data.php';
        
        // Проверяем что $config - массив
        if (!is_array($this->config)) {
            throw new \RuntimeException('Configuration file must return an array');
        }
        
        // Проверяем обязательные поля
        $required = ['sub_domain', 'client_id', 'client_secret', 'code', 'redirect_url', 'pipeline_id'];
        foreach ($required as $field) {
            if (!isset($this->config[$field])) {
                throw new \RuntimeException("Missing required config field: {$field}");
            }
        }
        
        // Создаем клиент AmoCRM
        $this->amoClient = new AmoCrmV4Client(
            $this->config['sub_domain'],
            $this->config['client_id'],
            $this->config['client_secret'],
            $this->config['code'],
            $this->config['redirect_url']
        );
    }
    
    /**
     * Основной метод обработки
     * Находит сделки ТОЛЬКО на этапе "Заявка" с бюджетом > 5000 
     * и перемещает их на этап "Ожидание клиента"
     */
    public function handle() {
        try {
            // 1. Получаем параметры
            $params = $this->getProcessingParameters();
            
            // 2. Проверяем обязательные параметры
            $this->validateParameters($params);
            
            // 3. Получаем ВСЕ сделки на стадии "Заявка"
            $leads = $this->getLeadsOnApplicationStage($params);
            
            if (empty($leads)) {
                return $this->createResponse(false, 'Не найдено сделок на стадии "Заявка"', [
                    'total_leads' => 0,
                    'moved_count' => 0,
                    'parameters' => $params
                ]);
            }
            
            // 4. Фильтруем сделки по бюджету (> 5000)
            $filteredLeads = $this->filterLeadsByBudget($leads, $params['budget_threshold']);
            
            if (empty($filteredLeads)) {
                return $this->createResponse(false, 'Не найдено сделок на стадии "Заявка" с бюджетом > ' . $params['budget_threshold'], [
                    'total_leads' => count($leads),
                    'filtered_leads' => 0,
                    'moved_count' => 0,
                    'parameters' => $params
                ]);
            }
            
            // 5. Перемещаем ОТФИЛЬТРОВАННЫЕ сделки на стадию "Ожидание клиента"
            $movedLeads = $this->moveFilteredLeads($filteredLeads, $params);
            
            // 6. Формируем успешный ответ
            return $this->createResponse(true, 'Обработка завершена успешно', [
                'total_leads_on_application_stage' => count($leads),
                'leads_with_budget_over_threshold' => count($filteredLeads),
                'successfully_moved' => count($movedLeads['success']),
                'failed_to_move' => count($movedLeads['failed']),
                'success_leads' => array_slice($movedLeads['success'], 0, 10),
                'failed_leads' => array_slice($movedLeads['failed'], 0, 10),
                'parameters' => $params
            ]);
            
        } catch (\Exception $e) {
            error_log("MoveLeadsHandler error: " . $e->getMessage());
            return $this->createResponse(false, 'Ошибка обработки: ' . $e->getMessage(), [
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
        }
    }
    
    /**
     * Получает параметры обработки (из конфига или запроса)
     */
    private function getProcessingParameters() {
        // Берем параметры по умолчанию из конфига
        $defaultParams = [
            'pipeline_id' => $this->config['pipeline_id'],
            'application_stage_id' => $this->config['application_stage_id'] ?? null,
            'waiting_stage_id' => $this->config['waiting_stage_id'] ?? null,
            'budget_threshold' => $this->config['budget_threshold'] ?? 5000,
            'limit' => 250
        ];
        
        // Пробуем получить параметры из запроса
        $requestParams = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $json = file_get_contents('php://input');
            if (!empty($json)) {
                $data = json_decode($json, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $requestParams = $data;
                }
            }
        } else {
            $requestParams = $_GET;
        }
        
        // Объединяем (параметры запроса имеют приоритет над конфигом)
        return array_merge($defaultParams, $requestParams);
    }
    
    /**
     * Проверяет обязательные параметры
     */
    private function validateParameters($params) {
        $required = ['application_stage_id', 'waiting_stage_id'];
        
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \RuntimeException("Не указан обязательный параметр: {$field}. Проверьте конфиг или передайте в запросе.");
            }
        }
        
        if ($params['application_stage_id'] == $params['waiting_stage_id']) {
            throw new \RuntimeException("ID исходной и целевой стадий не должны совпадать");
        }
    }
    
    /**
     * Получает ВСЕ сделки на стадии "Заявка"
     */
    private function getLeadsOnApplicationStage($params) {
        try {
            // Формируем запрос ТОЛЬКО для стадии "Заявка"
            $queryParams = [
                'limit' => $params['limit'],
                'with' => 'contacts,custom_fields',
                'filter[status_id][]' => (int)$params['application_stage_id'],
                'filter[pipeline_id][]' => (int)$params['pipeline_id']
            ];
            
            $allLeads = $this->amoClient->GETAll('leads', $queryParams);
            
            // Дополнительная проверка: оставляем только сделки на стадии "Заявка"
            $verifiedLeads = [];
            foreach ($allLeads as $lead) {
                if (($lead['status_id'] ?? null) == $params['application_stage_id']) {
                    $verifiedLeads[] = $lead;
                }
            }
            
            return $verifiedLeads;
            
        } catch (\Exception $e) {
            error_log("Error getting leads on application stage: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Фильтрует сделки по бюджету
     * Возвращает только те, у которых бюджет > threshold
     */
    private function filterLeadsByBudget($leads, $budgetThreshold) {
        $filtered = [];
        
        foreach ($leads as $lead) {
            $budget = $this->extractBudgetFromLead($lead);
            
            if ($budget > $budgetThreshold) {
                $filtered[] = [
                    'id' => $lead['id'],
                    'name' => $lead['name'] ?? 'Без названия',
                    'budget' => $budget,
                    'status_id' => $lead['status_id'],
                    'pipeline_id' => $lead['pipeline_id'],
                    'original_data' => $lead
                ];
            }
        }
        
        return $filtered;
    }
    
    /**
     * Извлекает бюджет из данных сделки
     */
    private function extractBudgetFromLead($lead) {
        // 1. Основное поле 'price' (самый вероятный вариант)
        if (isset($lead['price']) && is_numeric($lead['price'])) {
            return (float)$lead['price'];
        }
        
        // 2. Кастомные поля
        if (isset($lead['custom_fields_values'])) {
            foreach ($lead['custom_fields_values'] as $field) {
                $fieldName = strtolower($field['field_name'] ?? '');
                $fieldCode = strtolower($field['field_code'] ?? '');
                
                // Расширенный список названий для бюджета
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
        
        return 0;
    }
    
    /**
     * Перемещает отфильтрованные сделки на стадию "Ожидание клиента"
     */
    private function moveFilteredLeads($leads, $params) {
        $result = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($leads as $lead) {
            try {
                // Проверяем, что сделка всё еще на стадии "Заявка"
                $currentLead = $this->amoClient->GET('leads', $lead['id']);
                $currentStage = $currentLead['status_id'] ?? null;
                
                if ($currentStage != $params['application_stage_id']) {
                    $result['failed'][] = [
                        'id' => $lead['id'],
                        'name' => $lead['name'],
                        'error' => 'Сделка больше не на стадии "Заявка"',
                        'current_stage' => $currentStage,
                        'expected_stage' => $params['application_stage_id']
                    ];
                    continue;
                }
                
                // Подготавливаем данные для обновления
                $updateData = [
                    'id' => $lead['id'],
                    'status_id' => $params['waiting_stage_id'],
                    'updated_at' => time()
                ];
                
                // Отправляем запрос на обновление
                $response = $this->amoClient->PATCH('leads', [$updateData]);
                
                // Проверяем ответ
                if (!empty($response) && isset($response['_embedded']['leads'][0]['id'])) {
                    // Двойная проверка: убедимся что статус изменился
                    usleep(500000); // Ждем 0.5 секунды
                    $updatedLead = $this->amoClient->GET('leads', $lead['id']);
                    $newStage = $updatedLead['status_id'] ?? null;
                    
                    if ($newStage == $params['waiting_stage_id']) {
                        $result['success'][] = [
                            'id' => $lead['id'],
                            'name' => $lead['name'],
                            'budget' => $lead['budget'],
                            'old_stage' => $params['application_stage_id'],
                            'new_stage' => $params['waiting_stage_id'],
                            'confirmed' => true
                        ];
                    } else {
                        $result['failed'][] = [
                            'id' => $lead['id'],
                            'name' => $lead['name'],
                            'error' => 'Статус не изменился после обновления',
                            'current_stage' => $newStage,
                            'expected_stage' => $params['waiting_stage_id']
                        ];
                    }
                } else {
                    $result['failed'][] = [
                        'id' => $lead['id'],
                        'name' => $lead['name'],
                        'error' => 'Не удалось обновить сделку',
                        'response' => $response
                    ];
                }
                
            } catch (\Exception $e) {
                $result['failed'][] = [
                    'id' => $lead['id'],
                    'name' => $lead['name'],
                    'error' => $e->getMessage(),
                    'budget' => $lead['budget'] ?? 0
                ];
            }
            
            // Пауза между запросами
            usleep(300000); // 300ms
        }
        
        return $result;
    }
    
    /**
     * Создает структурированный ответ
     */
    private function createResponse($success, $message, $data = []) {
        return [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
    }
}