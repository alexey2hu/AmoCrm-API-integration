<?php

namespace App\Handlers;

use App\Clients\AmoCrmV4Client;

class CopyLeadsHandler {
    private $amoClient;
    private $config;
    private $debugMode = false; // Режим отладки
    
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
        
        // Включаем отладку если есть параметр debug
        $this->debugMode = isset($_GET['debug']) || isset($_POST['debug']);
    }
    
    /**
     * Основной метод обработки
     * Копирует сделки на стадии "Клиент подтвердил" с бюджетом = 4999
     */
    public function handle() {
        try {
            // 1. Получаем параметры
            $params = $this->getProcessingParameters();
            
            if ($this->debugMode) {
                echo "<pre>=== НАЧАЛО ОБРАБОТКИ ===\n";
                echo "Параметры: " . print_r($params, true) . "</pre>";
            }
            
            // 2. Проверяем обязательные параметры
            $this->validateParameters($params);
            
            // 3. Получаем сделки на стадии "Клиент подтвердил" с бюджетом = 4999
            $leads = $this->getLeadsOnClientConfirmedStage($params);
            
            if ($this->debugMode) {
                echo "<pre>Найдено сделок на стадии 'Клиент подтвердил': " . count($leads) . "</pre>";
            }
            
            if (empty($leads)) {
                return $this->createResponse(false, 'Не найдено сделок на стадии "Клиент подтвердил" с бюджетом = ' . $params['copy_budget_value'], [
                    'total_leads' => 0,
                    'copied_count' => 0,
                    'parameters' => $params
                ]);
            }
            
            if ($this->debugMode) {
                echo "<pre>=== НАЙДЕННЫЕ СДЕЛКИ ===\n";
                foreach ($leads as $lead) {
                    echo "ID: {$lead['id']}, Название: {$lead['name']}, Бюджет: {$lead['budget']}\n";
                }
                echo "=== НАЧИНАЕМ КОПИРОВАНИЕ ===</pre>";
            }
            
            // 4. Копируем найденные сделки
            $copyResults = $this->copyLeadsWithNotesAndTasks($leads, $params);
            
            if ($this->debugMode) {
                echo "<pre>=== РЕЗУЛЬТАТЫ КОПИРОВАНИЯ ===\n";
                echo "Успешно скопировано: " . count($copyResults['success']) . "\n";
                echo "Не удалось скопировать: " . count($copyResults['failed']) . "\n";
                echo "</pre>";
            }
            
            // 5. Формируем ответ
            return $this->createResponse(true, 'Копирование завершено', [
                'total_leads_found' => count($leads),
                'successfully_copied' => count($copyResults['success']),
                'failed_to_copy' => count($copyResults['failed']),
                'success_copies' => array_slice($copyResults['success'], 0, 5),
                'failed_copies' => array_slice($copyResults['failed'], 0, 5),
                'parameters' => $params,
                'pipeline_name' => $this->getPipelineName($params['pipeline_id'])
            ]);
            
        } catch (\Exception $e) {
            error_log("CopyLeadsHandler error: " . $e->getMessage());
            return $this->createResponse(false, 'Ошибка обработки: ' . $e->getMessage(), [
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
        }
    }
    
    /**
     * Получает название воронки по ID
     */
    private function getPipelineName($pipelineId) {
        try {
            $pipeline = $this->amoClient->GET('leads/pipelines', $pipelineId);
            return $pipeline['name'] ?? 'Неизвестная воронка';
        } catch (\Exception $e) {
            return 'Не удалось получить название воронки';
        }
    }
    
    /**
     * Получает параметры обработки
     */
    private function getProcessingParameters() {
        // Параметры по умолчанию из конфига
					$defaultParams = [
						'pipeline_id' => $this->config['pipeline_id'],
						'client_confirmed_stage_id' => $this->config['client_confirmed_stage_id'] ?? null,
						'copy_to_stage_id' => $this->config['waiting_stage_id'] ?? null,
						'copy_budget_value' => $this->config['copy_budget_value'] ?? 4999,
						'limit' => 50
				];
        
        // Параметры из запроса
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
        $result = array_merge($defaultParams, $requestParams);
        
        // Добавляем отладочную информацию
        if ($this->debugMode) {
            $result['debug_info'] = [
                'config_pipeline_id' => $this->config['pipeline_id'],
                'working_with_pipeline' => $result['pipeline_id']
            ];
        }
        
        return $result;
    }
    
    /**
     * Проверяет обязательные параметры
     */
    private function validateParameters($params) {
        $required = ['client_confirmed_stage_id', 'copy_to_stage_id'];
        
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \RuntimeException("Не указан обязательный параметр: {$field}. Проверьте конфиг или передайте в запросе.");
            }
        }
        
        if ($params['client_confirmed_stage_id'] == $params['copy_to_stage_id']) {
            throw new \RuntimeException("ID исходной и целевой стадий не должны совпадать");
        }
        
        if ($this->debugMode) {
            echo "<pre>=== ПРОВЕРКА ПАРАМЕТРОВ ===\n";
            echo "Pipeline ID: {$params['pipeline_id']}\n";
            echo "Стадия 'Клиент подтвердил': {$params['client_confirmed_stage_id']}\n";
            echo "Стадия для копирования: {$params['copy_to_stage_id']}\n";
            echo "Бюджет для фильтрации: {$params['copy_budget_value']}\n";
            echo "========================</pre>";
        }
    }
    
    /**
     * Получает сделки на стадии "Клиент подтвердил" с бюджетом = 4999
     */
    private function getLeadsOnClientConfirmedStage($params) {
        try {
            if ($this->debugMode) {
                echo "<pre>=== ЗАПРОС СДЕЛОК ===\n";
                echo "Ищем сделки в воронке ID: {$params['pipeline_id']}\n";
                echo "На стадии ID: {$params['client_confirmed_stage_id']}\n";
                echo "С бюджетом = {$params['copy_budget_value']}\n";
                echo "</pre>";
            }
            
            // Получаем все сделки на стадии "Клиент подтвердил"
            $queryParams = [
                'limit' => $params['limit'],
                'with' => 'contacts,custom_fields',
                'filter[status_id][]' => (int)$params['client_confirmed_stage_id'],
                'filter[pipeline_id][]' => (int)$params['pipeline_id']
            ];
            
            $allLeads = $this->amoClient->GETAll('leads', $queryParams);
            
            if ($this->debugMode) {
                echo "<pre>Найдено сделок всего: " . count($allLeads) . "</pre>";
            }
            
            // Фильтруем по бюджету = 4999
            $filteredLeads = [];
            foreach ($allLeads as $lead) {
                $budget = $this->extractBudgetFromLead($lead);
                
                if ($this->debugMode) {
                    echo "<pre>Проверяем сделку ID: {$lead['id']}, Название: {$lead['name']}\n";
                    echo "Бюджет извлеченный: {$budget}\n";
                    echo "Требуемый бюджет: {$params['copy_budget_value']}\n";
                }
                
                if (abs($budget - $params['copy_budget_value']) < 0.01) { // точное сравнение с учетом float
                    $filteredLeads[] = [
                        'id' => $lead['id'],
                        'name' => $lead['name'] ?? 'Без названия',
                        'budget' => $budget,
                        'status_id' => $lead['status_id'],
                        'pipeline_id' => $lead['pipeline_id'],
                        'original_data' => $lead
                    ];
                    
                    if ($this->debugMode) {
                        echo "✓ Подходит для копирования\n";
                    }
                } else {
                    if ($this->debugMode) {
                        echo "✗ Не подходит (бюджет не равен {$params['copy_budget_value']})\n";
                    }
                }
                
                if ($this->debugMode) {
                    echo "---</pre>";
                }
            }
            
            return $filteredLeads;
            
        } catch (\Exception $e) {
            error_log("Error getting leads on client confirmed stage: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Извлекает бюджет из данных сделки
     */
    private function extractBudgetFromLead($lead) {
        // 1. Основное поле 'price'
        if (isset($lead['price']) && is_numeric($lead['price'])) {
            return (float)$lead['price'];
        }
        
        // 2. Кастомные поля
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
        
        return 0;
    }
    
    /**
     * Копирует сделки с примечаниями и задачами
     */
    private function copyLeadsWithNotesAndTasks($leads, $params) {
        $result = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($leads as $index => $lead) {
            if ($this->debugMode) {
							echo "<pre>=== КОПИРОВАНИЕ СДЕЛКИ " . ($index + 1) . "/" . count($leads) . " ===\n";
              echo "ID: {$lead['id']}, Название: {$lead['name']}\n";
            }
            
            try {
                // a) Получаем полные данные сделки
                if ($this->debugMode) echo "1. Получаем полные данные сделки...\n";
                $fullLead = $this->amoClient->GET('leads', $lead['id']);
                
                // b) Получаем примечания сделки
                if ($this->debugMode) echo "2. Получаем примечания...\n";
                $notes = $this->getLeadNotes($lead['id']);
                if ($this->debugMode) echo "   Найдено примечаний: " . count($notes) . "\n";
                
                // c) Получаем задачи сделки
                if ($this->debugMode) echo "3. Получаем задачи...\n";
                $tasks = $this->getLeadTasks($lead['id']);
                if ($this->debugMode) echo "   Найдено задач: " . count($tasks) . "\n";
                
                // d) Создаем копию сделки на новой стадии
                if ($this->debugMode) echo "4. Создаем копию сделки...\n";
                $newLeadId = $this->createLeadCopy($fullLead, $params['copy_to_stage_id']);
                
                if (!$newLeadId) {
                    throw new \Exception('Не удалось создать копию сделки');
                }
                
                if ($this->debugMode) echo "   Создана копия ID: {$newLeadId}\n";
                
                // e) Копируем примечания
                if ($this->debugMode) echo "5. Копируем примечания...\n";
                $copiedNotes = $this->copyNotesToNewLead($notes, $newLeadId);
                if ($this->debugMode) echo "   Скопировано примечаний: {$copiedNotes}\n";
                
                // f) Копируем задачи
                if ($this->debugMode) echo "6. Копируем задачи...\n";
                $copiedTasks = $this->copyTasksToNewLead($tasks, $newLeadId);
                if ($this->debugMode) echo "   Скопировано задач: {$copiedTasks}\n";
                
                $result['success'][] = [
                    'original_lead_id' => $lead['id'],
                    'original_lead_name' => $lead['name'],
                    'new_lead_id' => $newLeadId,
                    'notes_copied' => $copiedNotes,
                    'tasks_copied' => $copiedTasks,
                    'budget' => $lead['budget']
                ];
                
                if ($this->debugMode) echo "✓ Успешно скопировано\n";
                
            } catch (\Exception $e) {
                $result['failed'][] = [
                    'original_lead_id' => $lead['id'],
                    'original_lead_name' => $lead['name'],
                    'error' => $e->getMessage(),
                    'budget' => $lead['budget']
                ];
                
                if ($this->debugMode) echo "✗ Ошибка: " . $e->getMessage() . "\n";
            }
            
            if ($this->debugMode) {
                echo "=====================</pre>";
            }
            
            // Пауза между операциями
            usleep(500000); // 500ms
        }
        
        return $result;
    }
    
    // Остальные методы остаются без изменений...
    private function getLeadNotes($leadId) {
        try {
            $notes = $this->amoClient->GETAll("leads/{$leadId}/notes");
            return $notes ?: [];
        } catch (\Exception $e) {
            error_log("Error getting notes for lead {$leadId}: " . $e->getMessage());
            return [];
        }
    }
    
    private function getLeadTasks($leadId) {
        try {
            $tasks = $this->amoClient->GETAll("leads/{$leadId}/tasks");
            return $tasks ?: [];
        } catch (\Exception $e) {
            error_log("Error getting tasks for lead {$leadId}: " . $e->getMessage());
            return [];
        }
    }
    
    private function createLeadCopy($originalLead, $targetStageId) {
        try {
            // Подготавливаем данные для новой сделки
            $newLeadData = [
                'name' => $originalLead['name'] . ' (копия)',
                'price' => $originalLead['price'] ?? 0,
                'status_id' => $targetStageId,
                'pipeline_id' => $originalLead['pipeline_id'],
                'created_by' => 0,
                'updated_by' => 0,
                'created_at' => time(),
                'updated_at' => time()
            ];
            
            // Копируем кастомные поля
            if (isset($originalLead['custom_fields_values'])) {
                $newLeadData['custom_fields_values'] = $originalLead['custom_fields_values'];
            }
            
            // Копируем контакты
            if (isset($originalLead['_embedded']['contacts'])) {
                $newLeadData['_embedded']['contacts'] = [];
                foreach ($originalLead['_embedded']['contacts'] as $contact) {
                    $newLeadData['_embedded']['contacts'][] = [
                        'id' => $contact['id'],
                        'is_main' => $contact['is_main'] ?? false
                    ];
                }
            }
            
            // Создаем сделку
            $response = $this->amoClient->POST('leads', [$newLeadData]);
            
            if (!empty($response) && isset($response['_embedded']['leads'][0]['id'])) {
                return $response['_embedded']['leads'][0]['id'];
            }
            
            return null;
            
        } catch (\Exception $e) {
            error_log("Error creating lead copy: " . $e->getMessage());
            return null;
        }
    }
    
    private function copyNotesToNewLead($notes, $newLeadId) {
        $copiedCount = 0;
        
        foreach ($notes as $note) {
            try {
                $noteData = [
                    'entity_id' => $newLeadId,
                    'note_type' => $note['note_type'] ?? 'common',
                    'params' => $note['params'] ?? [],
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => time(),
                    'updated_at' => time()
                ];
                
                // Копируем текст примечания
                if (isset($note['params']['text'])) {
                    $noteData['params']['text'] = $note['params']['text'];
                }
                
                $response = $this->amoClient->POST("leads/{$newLeadId}/notes", [$noteData]);
                
                if (!empty($response) && isset($response['_embedded']['notes'][0]['id'])) {
                    $copiedCount++;
                }
                
            } catch (\Exception $e) {
                error_log("Error copying note to lead {$newLeadId}: " . $e->getMessage());
            }
            
            usleep(100000);
        }
        
        return $copiedCount;
    }
    
    private function copyTasksToNewLead($tasks, $newLeadId) {
        $copiedCount = 0;
        
        foreach ($tasks as $task) {
            try {
                $taskData = [
                    'entity_id' => $newLeadId,
                    'entity_type' => 'leads',
                    'task_type_id' => $task['task_type_id'] ?? 1,
                    'text' => $task['text'] ?? '',
                    'complete_till' => $task['complete_till'] ?? time() + 86400,
                    'responsible_user_id' => $task['responsible_user_id'] ?? 0,
                    'created_by' => 0,
                    'updated_by' => 0,
                    'created_at' => time(),
                    'updated_at' => time(),
                    'is_completed' => $task['is_completed'] ?? false,
                    'result' => $task['result'] ?? []
                ];
                
                $response = $this->amoClient->POST("tasks", [$taskData]);
                
                if (!empty($response) && isset($response['_embedded']['tasks'][0]['id'])) {
                    $copiedCount++;
                }
                
            } catch (\Exception $e) {
                error_log("Error copying task to lead {$newLeadId}: " . $e->getMessage());
            }
            
            usleep(100000);
        }
        
        return $copiedCount;
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