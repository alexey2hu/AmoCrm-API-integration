<?php
        $config = require __DIR__ . '/src/Config/data.php';
        header('Content-Type: application/json');
        echo json_encode([
            'pipeline_id' => $config['pipeline_id'] ?? null,
            'application_stage_id' => $config['application_stage_id'] ?? null,
            'waiting_stage_id' => $config['waiting_stage_id'] ?? null,
            'client_confirmed_stage_id' => $config['client_confirmed_stage_id'] ?? null,
            'budget_threshold' => $config['budget_threshold'] ?? 5000,
            'copy_budget_value' => $config['copy_budget_value'] ?? 4999
        ], JSON_UNESCAPED_UNICODE);
        ?>