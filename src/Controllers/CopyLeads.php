<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Handlers\CopyLeadsHandler;

class CopyLeads extends BaseController
{
    public function handle(): void
    {
        try {
            $handler = new CopyLeadsHandler();
            $result = $handler->handle();
            
            $this->sendJsonResponse($result);
            
        } catch (\Throwable $e) {
            $this->sendErrorResponse($e);
        }
    }
}