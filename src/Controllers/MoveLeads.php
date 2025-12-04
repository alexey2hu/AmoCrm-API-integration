<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Handlers\MoveLeadsHandler;

use Throwable;

// Контроллер для обработки событий смены ответственного лица.
class MoveLeads
{
     // Выполняет проверку лицензии и ставит задачу в очередь RabbitMQ для асинхронной обработки.
    public function handle(): void
    {
        header('Content-Type: application/json');

        try {
            $handler = new MoveLeadsHandler();
            $result = $handler->handle();
            
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            
        } catch (Throwable $e) {
            http_response_code(500);
            
            // В Handler уже есть логика создания ошибок, но на всякий случай
            echo json_encode([
                'success' => false,
                'message' => 'Internal Server Error: ' . $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s'),
                'data' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }
}