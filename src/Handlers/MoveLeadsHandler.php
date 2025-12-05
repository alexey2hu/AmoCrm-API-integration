<?php

namespace App\Handlers;

class MoveLeadsHandler extends BaseHandler
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
            
            // 3. Получаем сделки на стадии "Заявка"
            $leads = $this->getLeadsOnApplicationStage($params);
            
            if (empty($leads)) {
                return $this->createResponse(false, 'Не найдено сделок на стадии "Заявка"', [
                    'total_leads' => 0,
                    'moved_count' => 0,
                    'parameters' => $params
                ]);
            }
            
            // 4. Фильтруем по бюджету
            $filteredLeads = $this->filterLeadsByBudget($leads, $params['budget_threshold']);
            
            if (empty($filteredLeads)) {
                return $this->createResponse(false, 'Не найдено сделок с бюджетом > ' . $params['budget_threshold'], [
                    'total_leads' => count($leads),
                    'filtered_leads' => 0,
                    'moved_count' => 0,
                    'parameters' => $params
                ]);
            }
            
            // 5. Перемещаем сделки (ИСПРАВЛЕНО: правильное имя метода)
            $moveResults = $this->moveFilteredLeads($filteredLeads, $params);
            
            return $this->createResponse(true, 'Перемещение завершено', [
                'total_leads' => count($leads), // Все сделки на стадии "Заявка"
                'filtered_leads' => count($filteredLeads), // Сделки с бюджетом > порога
                'successfully_moved' => count($moveResults['success']),
                'failed_to_move' => count($moveResults['failed']),
                'success_leads' => array_slice($moveResults['success'], 0, 10),
                'failed_leads' => array_slice($moveResults['failed'], 0, 10),
                'parameters' => $params
            ]);
            
        } catch (\Exception $e) {
            error_log("MoveLeadsHandler error: " . $e->getMessage());
            return $this->createResponse(false, 'Ошибка обработки: ' . $e->getMessage());
        }
    }
    
    /**
     * Проверяет обязательные параметры
     */
    private function validateParameters(array $params): void
    {
        if (empty($params['application_stage_id']) || empty($params['waiting_stage_id'])) {
            throw new \RuntimeException("Не указаны обязательные параметры стадий");
        }
        
        if ($params['application_stage_id'] == $params['waiting_stage_id']) {
            throw new \RuntimeException("ID исходной и целевой стадий не должны совпадать");
        }
    }
    
    /**
     * Получает ВСЕ сделки на стадии "Заявка"
     */
    private function getLeadsOnApplicationStage(array $params): array
    {
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
    private function filterLeadsByBudget(array $leads, float $budgetThreshold): array
    {
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
     * Перемещает отфильтрованные сделки на стадию "Ожидание клиента"
     */
    private function moveFilteredLeads(array $leads, array $params): array
    {
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
                        'response' => $response ?? 'Пустой ответ'
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
}