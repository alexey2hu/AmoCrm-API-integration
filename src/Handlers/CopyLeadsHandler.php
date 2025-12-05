<?php

namespace App\Handlers;

class CopyLeadsHandler extends BaseHandler
{
    /**
     * Основной метод обработки запроса на копирование сделок
     * @return array Структурированный ответ с результатами операции
     */
    public function handle(): array
    {
        try {
            // 1. Получаем параметры обработки из конфигурации
            $params = $this->getProcessingParameters();
            
            // 2. Проверяем обязательные параметры для работы
            $this->validateParameters($params);
            
            // 3. Получаем сделки для копирования (с учетом бюджета)
            $leadsData = $this->getLeadsToCopy($params);
            $leads = $leadsData['leads'];
            $totalLeadsOnStage = $leadsData['total_leads_on_stage'];
            
            // Если сделок для копирования не найдено
            if (empty($leads)) {
                return $this->createResponse(false, 'Не найдено сделок для копирования', [
                    'total_leads' => $totalLeadsOnStage,      // Все сделки на этапе "Клиент подтвердил"
                    'filtered_leads' => 0,                    // Сделки с бюджетом = 4999
                    'copied_count' => 0,
                    'parameters' => $params
                ]);
            }
            
            // 4. Копируем найденные сделки вместе с примечаниями и задачами
            $copyResults = $this->copyLeadsWithNotesAndTasks($leads, $params);
            
            // 5. Формируем финальный ответ с результатами
            return $this->createResponse(true, 'Копирование завершено', [
                'total_leads' => $totalLeadsOnStage,                // Все сделки на этапе "Клиент подтвердил"
                'filtered_leads' => count($leads),                  // Сделки с бюджетом = 4999
                'total_leads_found' => count($leads),               // Для обратной совместимости
                'successfully_copied' => count($copyResults['success']),
                'failed_to_copy' => count($copyResults['failed']),
                'success_copies' => array_slice($copyResults['success'], 0, 5),    // Первые 5 успешных
                'failed_copies' => array_slice($copyResults['failed'], 0, 5),      // Первые 5 ошибок
                'parameters' => $params,
                'pipeline_name' => $this->getPipelineName($params['pipeline_id'])  // Название воронки
            ]);
            
        } catch (\Exception $e) {
            // Логируем ошибку и возвращаем сообщение пользователю
            error_log("CopyLeadsHandler error: " . $e->getMessage());
            return $this->createResponse(false, 'Ошибка обработки: ' . $e->getMessage());
        }
    }
    
    /**
     * Проверяет обязательные параметры для работы обработчика
     * @throws \RuntimeException если параметры невалидны
     */
    private function validateParameters(array $params): void
    {
        $required = ['client_confirmed_stage_id', 'waiting_stage_id'];
        
        // Проверяем наличие обязательных полей
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \RuntimeException("Не указан обязательный параметр: {$field}");
            }
        }
        
        // Исходная и целевая стадии не должны совпадать
        if ($params['client_confirmed_stage_id'] == $params['waiting_stage_id']) {
            throw new \RuntimeException("ID исходной и целевой стадий не должны совпадать");
        }
    }
    
    /**
     * Получает название воронки по ID
     * @return string Название воронки или сообщение об ошибке
     */
    private function getPipelineName(int $pipelineId): string
    {
        try {
            // Получаем список всех воронок
            $pipelines = $this->amoClient->GET('leads/pipelines');
            
            // Ищем воронку с нужным ID
            if (!empty($pipelines['_embedded']['pipelines'])) {
                foreach ($pipelines['_embedded']['pipelines'] as $pipeline) {
                    if ($pipeline['id'] == $pipelineId) {
                        return $pipeline['name'];
                    }
                }
            }
            
            return 'Неизвестная воронка (ID: ' . $pipelineId . ')';
        } catch (\Exception $e) {
            return 'Не удалось получить название воронки: ' . $e->getMessage();
        }
    }
    
    /**
     * Получает сделки для копирования с фильтрацией по бюджету
     * @return array [
     *     'leads' => массив сделок для копирования,
     *     'total_leads_on_stage' => общее количество сделок на этапе
     * ]
     */
    private function getLeadsToCopy(array $params): array
    {
        $queryParams = [
            'limit' => $params['limit'],
            'with' => 'contacts,custom_fields',
            'filter[status_id][]' => (int)$params['client_confirmed_stage_id'],
            'filter[pipeline_id][]' => (int)$params['pipeline_id']
        ];
        
        // Получаем все сделки с учетом фильтров
        $allLeads = $this->amoClient->GETAll('leads', $queryParams);
        
        $totalLeadsOnStage = 0;
        $leadsToCopy = [];
        
        foreach ($allLeads as $lead) {
            // Проверяем стадию и воронку
            if (($lead['status_id'] ?? null) == $params['client_confirmed_stage_id'] 
                && ($lead['pipeline_id'] ?? null) == $params['pipeline_id']) {
                
                $totalLeadsOnStage++; // Считаем ВСЕ сделки на этапе "Клиент подтвердил"
                $budget = $this->extractBudgetFromLead($lead);
                
                // Проверяем бюджет на точное совпадение с copy_budget_value
                if (abs($budget - $params['copy_budget_value']) < 0.01) {
                    $leadsToCopy[] = [
                        'id' => $lead['id'],
                        'name' => $lead['name'] ?? 'Без названия',
                        'budget' => $budget,
                        'original_data' => $lead
                    ];
                }
            }
        }
        
        return [
            'leads' => $leadsToCopy,
            'total_leads_on_stage' => $totalLeadsOnStage
        ];
    }
    
    /**
     * Копирует сделки вместе с примечаниями и задачами
     * @return array [
     *     'success' => массив успешно скопированных сделок,
     *     'failed' => массив неудачных попыток
     * ]
     */
    private function copyLeadsWithNotesAndTasks(array $leads, array $params): array
    {
        $result = [
            'success' => [],
            'failed' => []
        ];
        
        foreach ($leads as $lead) {
            try {
                // 1. Создаем копию сделки
                $newLeadId = $this->createLeadCopy($lead['original_data'], $params['waiting_stage_id']);
                
                if (!$newLeadId) {
                    throw new \Exception('Не удалось создать копию сделки');
                }
                
                // 2. Копируем примечания из оригинала
                $notes = $this->getLeadNotes($lead['id']);
                $copiedNotes = $this->copyNotesToNewLead($notes, $newLeadId);
                
                // 3. Копируем задачи из оригинала
                $tasks = $this->getLeadTasks($lead['id']);
                $copiedTasks = $this->copyTasksToNewLead($tasks, $newLeadId);
                
                $result['success'][] = [
                    'original_lead_id' => $lead['id'],
                    'new_lead_id' => $newLeadId,
                    'notes_copied' => $copiedNotes,
                    'tasks_copied' => $copiedTasks
                ];
                
            } catch (\Exception $e) {
                $result['failed'][] = [
                    'original_lead_id' => $lead['id'],
                    'error' => $e->getMessage()
                ];
            }
            
            usleep(500000); // 500ms пауза между запросами
        }
        
        return $result;
    }
    
    /**
     * Получает примечания сделки
     */
    private function getLeadNotes($leadId): array
    {
        try {
            $notes = $this->amoClient->GETAll("leads/{$leadId}/notes");
            return $notes ?: [];
        } catch (\Exception $e) {
            error_log("Error getting notes for lead {$leadId}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получает задачи сделки
     */
    private function getLeadTasks($leadId): array
    {
        try {
            $tasks = $this->amoClient->GETAll("leads/{$leadId}/tasks");
            return $tasks ?: [];
        } catch (\Exception $e) {
            error_log("Error getting tasks for lead {$leadId}: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Создает копию сделки
     * @return int|null ID новой сделки или null при ошибке
     */
    private function createLeadCopy($originalLead, $targetStageId): ?int
    {
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
            
            // Создаем сделку через API
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
    
    /**
     * Копирует примечания в новую сделку
     * @return int Количество успешно скопированных примечаний
     */
    private function copyNotesToNewLead($notes, $newLeadId): int
    {
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
            
            usleep(100000); // 100ms пауза
        }
        
        return $copiedCount;
    }
    
    /**
     * Копирует задачи в новую сделку
     * @return int Количество успешно скопированных задач
     */
    private function copyTasksToNewLead($tasks, $newLeadId): int
    {
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
            
            usleep(100000); // 100ms пауза
        }
        
        return $copiedCount;
    }
}