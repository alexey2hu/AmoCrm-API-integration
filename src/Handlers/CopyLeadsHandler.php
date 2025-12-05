<?php

namespace App\Handlers;

class CopyLeadsHandler extends BaseHandler
{
    /**
     * Основной метод обработки
     */
    public function handle(): array
    {
        try {
            // 1. Получаем параметры
            $params = $this->getProcessingParameters();
            
            // 2. Проверяем обязательные параметры
            $this->validateParameters($params);
            
            // 3. Получаем сделки для копирования
            $leadsData = $this->getLeadsToCopy($params);
            $leads = $leadsData['leads']; // Извлекаем сделки
            $totalLeadsOnStage = $leadsData['total_leads_on_stage']; // Извлекаем общее количество
            
            if (empty($leads)) {
                return $this->createResponse(false, 'Не найдено сделок для копирования', [
                    'total_leads' => $totalLeadsOnStage, // Все сделки на этапе
                    'filtered_leads' => 0, // Сделки с бюджетом = 4999
                    'copied_count' => 0,
                    'parameters' => $params
                ]);
            }
            
            // 4. Копируем найденные сделки
            $copyResults = $this->copyLeadsWithNotesAndTasks($leads, $params);
            
            // 5. Формируем ответ
            return $this->createResponse(true, 'Копирование завершено', [
                'total_leads' => $totalLeadsOnStage, // Все сделки на этапе "Клиент подтвердил"
                'filtered_leads' => count($leads), // Сделки с бюджетом = 4999
                'total_leads_found' => count($leads), // Для обратной совместимости
                'successfully_copied' => count($copyResults['success']),
                'failed_to_copy' => count($copyResults['failed']),
                'success_copies' => array_slice($copyResults['success'], 0, 5),
                'failed_copies' => array_slice($copyResults['failed'], 0, 5),
                'parameters' => $params,
                'pipeline_name' => $this->getPipelineName($params['pipeline_id'])
            ]);
            
        } catch (\Exception $e) {
            error_log("CopyLeadsHandler error: " . $e->getMessage());
            return $this->createResponse(false, 'Ошибка обработки: ' . $e->getMessage());
        }
    }
    
    /**
     * Проверяет обязательные параметры
     */
    private function validateParameters(array $params): void
    {
        $required = ['client_confirmed_stage_id', 'waiting_stage_id'];
        
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \RuntimeException("Не указан обязательный параметр: {$field}");
            }
        }
        
        if ($params['client_confirmed_stage_id'] == $params['waiting_stage_id']) {
            throw new \RuntimeException("ID исходной и целевой стадий не должны совпадать");
        }
    }
    
    /**
     * Получает название воронки по ID
     */
    private function getPipelineName(int $pipelineId): string
		{
			try {
					// В GET должен быть только endpoint, без ID в параметрах
					$pipelines = $this->amoClient->GET('leads/pipelines');
					
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
         * Получает сделки для копирования
         */
        private function getLeadsToCopy(array $params): array
        {
            $queryParams = [
                'limit' => $params['limit'],
                'with' => 'contacts,custom_fields',
                'filter[status_id][]' => (int)$params['client_confirmed_stage_id'],
                'filter[pipeline_id][]' => (int)$params['pipeline_id']
            ];
            
            $allLeads = $this->amoClient->GETAll('leads', $queryParams);
            
            $totalLeadsOnStage = 0;
            $leadsToCopy = [];
            
            foreach ($allLeads as $lead) {
                // Проверяем стадию и воронку
                if (($lead['status_id'] ?? null) == $params['client_confirmed_stage_id'] 
                    && ($lead['pipeline_id'] ?? null) == $params['pipeline_id']) {
                    
                    $totalLeadsOnStage++; // Считаем ВСЕ сделки на этапе "Клиент подтвердил"
                    $budget = $this->extractBudgetFromLead($lead);
                    
                    // Проверяем бюджет на точное совпадение
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
            
            // Возвращаем массив с обеими переменными
            return [
                'leads' => $leadsToCopy,
                'total_leads_on_stage' => $totalLeadsOnStage
            ];
        }
    
    /**
     * Копирует сделки с примечаниями и задачами
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
                
                // 2. Копируем примечания
                $notes = $this->getLeadNotes($lead['id']);
                $copiedNotes = $this->copyNotesToNewLead($notes, $newLeadId);
                
                // 3. Копируем задачи
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
            
            usleep(500000); // 500ms пауза
        }
        
        return $result;
    }
    
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
}