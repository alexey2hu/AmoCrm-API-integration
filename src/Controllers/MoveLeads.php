<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Handlers\MoveLeadsHandler;

class MoveLeads extends BaseController // Изменено с MoveLeadsController на MoveLeads
{
    public function handle(): void
    {
        try {
            $handler = new MoveLeadsHandler();
            $result = $handler->handle();
            
            $this->sendJsonResponse($result);
            
        } catch (\Throwable $e) {
            $this->sendErrorResponse($e);
        }
    }
}