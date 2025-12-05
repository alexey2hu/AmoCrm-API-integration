<?php

namespace App\Handlers;

class MoveLeadsHandler extends BaseHandler
{
    /**
     * Основной метод обработки запроса на перемещение сделок
     * @return array Структурированный ответ с результатами операции
     */
    public function handle(): array
    {
        try {
            // 1. Получаем параметры обработки из конфигурации
            $params = $this->getProcessingParameters();
            
            // 2. Проверяем обязательные параметры для работы
            $this->validateParameters($params);
            
            // 3. Получаем все сделки на стадии "Заявка"
            $leads = $this->getLeadsOnApplicationStage($params);
            
            // Если сделок на стадии "Заявка" не найдено
            if (empty($leads)) {
                return $this->createResponse(false, 'Не найдено сделок на стадии "Заявка"', [
                    'total_leads' => 0,
                    'moved_count' => 0,
                    'parameters' => $params
                ]);
            }
            
            // 4. Фильтруем сделки по бюджету (бюджет > порогового значения)
            $filteredLeads = $this->filterLeadsByBudget($leads, $params['budget_threshold']);
            
            // Если нет сделок с бюджетом выше порога
            if (empty($filteredLeads)) {
                return $this->createResponse(false, 'Не найдено сделок с бюджетом > ' . $params['budget_threshold'], [
                    'total_leads' => count($leads),      // Все сделки на стадии "Заявка"
                    'filtered_leads' => 0,               // Сделки с бюджетом > порога
                    'moved_count' => 0,
                    'parameters' => $params
                ]);
            }
            
            // 5. Перемещаем отфильтрованные сделки на стадию "Ожидание клиента"
            $moveResults = $this->moveFilteredLeads($filteredLeads, $params);
            
            // 6. Формируем финальный ответ с результатами
            return $this->createResponse(true, 'Перемещение завершено', [
                'total_leads' => count($leads),                  // Все сделки на стадии "Заявка"
                'filtered_leads' => count($filteredLeads),       // Сделки с бюджетом > порога
                'successfully_moved' => count($moveResults['success']),
                'failed_to_move' => count($moveResults['failed']),
                'success_leads' => array_slice($moveResults['success'], 0, 10),  // Первые 10 успешных
                'failed_leads' => array_slice($moveResults['failed'], 0, 10),    // Первые 10 ошибок
                'parameters' => $params
            ]);
            
        } catch (\Exception $e) {
            // Логируем ошибку и возвращаем сообщение пользователю
            error_log("MoveLeadsHandler error: " . $e->getMessage());
            return $this->createResponse(false, 'Ошибка обработки: ' . $e->getMessage());
        }
    }
    
    /**
     * Проверяет обязательные параметры для работы обработчика
     * @throws \RuntimeException если параметры невалидны
     */
    private function validateParameters(array $params): void
    {
        // Проверяем наличие обязательных ID стадий
        if (empty($params['application_stage_id']) || empty($params['waiting_stage_id'])) {
            throw new \RuntimeException("Не указаны обязательные параметры стадий");
        }
        
        // Исходная и целевая стадии не должны совпадать
        if ($params['application_stage_id'] == $params['waiting_stage_id']) {
            throw new \RuntimeException("ID исходной и целевой стадий не должны совпадать");
        }
    }
    
    /**
     * Получает ВСЕ сделки на стадии "Заявка"
     * @return array Массив сделок на стадии "Заявка"
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
     * Возвращает только те сделки, у которых бюджет > порогового значения
     * @return array Отфильтрованные сделки с дополнительной информацией
     */
    private function filterLeadsByBudget(array $leads, float $budgetThreshold): array
    {
        $filtered = [];
        
        foreach ($leads as $lead) {
            // Извлекаем бюджет из сделки
            $budget = $this->extractBudgetFromLead($lead);
            
            // Отбираем только сделки с бюджетом выше порога
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
     * Выполняет двойную проверку успешности изменения стадии
     * @return array [
     *     'success' => массив успешно перемещенных сделок,
     *     'failed' => массив неудачных попыток перемещения
     * ]
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
                
                // Если сделка уже была перемещена другим процессом
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
                
                // Подготавливаем данные для обновления (только изменение стадии)
                $updateData = [
                    'id' => $lead['id'],
                    'status_id' => $params['waiting_stage_id'],
                    'updated_at' => time()
                ];
                
                // Отправляем запрос на обновление стадии
                $response = $this->amoClient->PATCH('leads', [$updateData]);
                
                // Проверяем ответ от API
                if (!empty($response) && isset($response['_embedded']['leads'][0]['id'])) {
                    // Двойная проверка: убедимся что статус действительно изменился
                    usleep(500000); // Ждем 0.5 секунды для синхронизации данных
                    $updatedLead = $this->amoClient->GET('leads', $lead['id']);
                    $newStage = $updatedLead['status_id'] ?? null;
                    
                    // Проверяем, что стадия успешно изменилась
                    if ($newStage == $params['waiting_stage_id']) {
                        $result['success'][] = [
                            'id' => $lead['id'],
                            'name' => $lead['name'],
                            'budget' => $lead['budget'],
                            'old_stage' => $params['application_stage_id'],
                            'new_stage' => $params['waiting_stage_id'],
                            'confirmed' => true  // Двойная проверка пройдена
                        ];
                    } else {
                        // Статус не изменился после обновления
                        $result['failed'][] = [
                            'id' => $lead['id'],
                            'name' => $lead['name'],
                            'error' => 'Статус не изменился после обновления',
                            'current_stage' => $newStage,
                            'expected_stage' => $params['waiting_stage_id']
                        ];
                    }
                } else {
                    // Ошибка при обновлении через API
                    $result['failed'][] = [
                        'id' => $lead['id'],
                        'name' => $lead['name'],
                        'error' => 'Не удалось обновить сделку',
                        'response' => $response ?? 'Пустой ответ'
                    ];
                }
                
            } catch (\Exception $e) {
                // Ошибка при обработке конкретной сделки
                $result['failed'][] = [
                    'id' => $lead['id'],
                    'name' => $lead['name'],
                    'error' => $e->getMessage(),
                    'budget' => $lead['budget'] ?? 0
                ];
            }
            
            // Пауза между запросами для избежания лимитов API
            usleep(300000); // 300ms
        }
        
        return $result;
    }
}