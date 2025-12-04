<?php
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
        $required = ['sub_domain', 'client_id', 'client_secret', 'code', 'redirect_url'];
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
    
    public function handle() {
        try {
            // Получаем входящие данные (для параметров фильтрации)
            $input = $this->getInputData();
            
            // Получаем сделки с учетом фильтров из входных данных
            $leads = $this->getLeadsFromAmoCRM($input);
            
            if (empty($leads)) {
                return [
                    'processed' => false,
                    'message' => 'No leads found',
                    'leads_count' => 0,
                    'input_data' => $input
                ];
            }
            
            // Реальная логика перемещения сделок
            $movedLeads = $this->moveLeadsToNewStage($leads, $input);
            
            return [
                'processed' => true,
                'moved_count' => count($movedLeads),
                'total_leads' => count($leads),
                'sample_moved' => array_slice($movedLeads, 0, 3),
                'input_data' => $input
            ];
            
        } catch (\Exception $e) {
            // Логируем и перебрасываем
            error_log("MoveLeadsHandler error: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function getInputData() {
        // Получаем данные из POST/GET или из тела запроса
        $input = [];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!empty($_POST)) {
                $input = $_POST;
            } else {
                // Пытаемся получить JSON из тела запроса
                $json = file_get_contents('php://input');
                if (!empty($json)) {
                    $data = json_decode($json, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $input = $data;
                    }
                }
            }
        } else {
            $input = $_GET;
        }
        
        // Параметры по умолчанию
        return array_merge([
            'limit' => 10,
            'with' => 'contacts',
            'target_stage_id' => $this->config['target_stage_id'] ?? null,
            'source_stage_id' => $this->config['source_stage_id'] ?? null
        ], $input);
    }
    
    private function getLeadsFromAmoCRM($params = []) {
        try {
            // Базовые параметры запроса
            $queryParams = [
                'limit' => $params['limit'] ?? 10,
                'with' => $params['with'] ?? 'contacts'
            ];
            
            // Добавляем фильтр по стадии, если указан
            if (!empty($params['source_stage_id'])) {
                $queryParams['filter']['pipeline_id'] = $params['pipeline_id'] ?? null;
                $queryParams['filter']['status_id'] = $params['source_stage_id'];
            }
            
            return $this->amoClient->GETAll('leads', $queryParams);
        } catch (\Exception $e) {
            error_log("Error getting leads from AmoCRM: " . $e->getMessage());
            return [];
        }
    }
    
    private function moveLeadsToNewStage($leads, $params) {
        $targetStageId = $params['target_stage_id'] ?? null;
        
        if (!$targetStageId) {
            throw new \RuntimeException('Target stage ID is not specified');
        }
        
        $movedLeads = [];
        
        foreach ($leads as $lead) {
            try {
                // Проверяем, что сделка имеет ID
                if (empty($lead['id'])) {
                    continue;
                }
                
                // Подготавливаем данные для обновления
                $updateData = [
                    'id' => $lead['id'],
                    'status_id' => $targetStageId,
                    'updated_at' => time()
                ];
                
                // Добавляем pipeline_id если нужно
                if (!empty($params['pipeline_id'])) {
                    $updateData['pipeline_id'] = $params['pipeline_id'];
                }
                
                // Обновляем сделку в AmoCRM
                $response = $this->amoClient->PATCH('leads', [$updateData]);
                
                if (!empty($response) && isset($response[0]['id'])) {
                    $movedLeads[] = [
                        'id' => $lead['id'],
                        'name' => $lead['name'] ?? 'Без названия',
                        'old_stage' => $lead['status_id'] ?? null,
                        'new_stage' => $targetStageId,
                        'success' => true
                    ];
                }
                
            } catch (\Exception $e) {
                error_log("Error moving lead ID {$lead['id']}: " . $e->getMessage());
                $movedLeads[] = [
                    'id' => $lead['id'],
                    'name' => $lead['name'] ?? 'Без названия',
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
        
        return $movedLeads;
    }
}