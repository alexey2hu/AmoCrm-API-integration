<?php

namespace App\Controllers;

use App\Handlers\CopyLeadsHandler;

class CopyLeads {
    private $handler;
    
    public function __construct() {
        $this->handler = new CopyLeadsHandler();
    }
    
    public function handle() {
        $result = $this->handler->handle();
        
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}