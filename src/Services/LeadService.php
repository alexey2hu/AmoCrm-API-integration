<?php

// src/Services/LeadService.php
namespace App\Services;

use App\Clients\AmoCrmV4Client;

class LeadService {
    private $amoClient;
    
    public function __construct(AmoCrmV4Client $amoClient) {
        $this->amoClient = $amoClient;
    }
    
    public function getLeadsByStage($stageId, $pipelineId = null, $limit = 10) {
        $queryParams = [
            'limit' => $limit,
            'with' => 'contacts'
        ];
        
        if ($stageId) {
            $queryParams['filter[status_id][]'] = (int)$stageId;
            if ($pipelineId) {
                $queryParams['filter[pipeline_id][]'] = (int)$pipelineId;
            }
        }
        
        return $this->amoClient->GETAll('leads', $queryParams);
    }
    
    public function updateLeadStage($leadId, $newStageId, $pipelineId = null) {
        $updateData = [
            'id' => $leadId,
            'status_id' => $newStageId,
            'updated_at' => time()
        ];
        
        if ($pipelineId) {
            $updateData['pipeline_id'] = $pipelineId;
        }
        
        return $this->amoClient->PATCH('leads', [$updateData]);
    }
}