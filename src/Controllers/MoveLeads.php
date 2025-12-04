<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Handlers\MoveLeadsHandler;

use Throwable;

/**
 * Контроллер для обработки событий смены ответственного лица.
 */
class MoveLeads
{
    /**
     * Выполняет проверку лицензии и ставит задачу в очередь RabbitMQ для асинхронной обработки.
     *
     * @return void
     */
    public function handle(): void
    {
        header('Content-Type: application/json');


        try {
            
            $handler = new MoveLeadsHandler();
            $handler->handle();

            echo json_encode(['status' => 'success', 'message' => null, 'data' => ''], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {

            echo json_encode(['status' => 'error', 'message' => 'Internal Server Error' . $e->getMessage(), 'data' => null], JSON_UNESCAPED_UNICODE);
        }
    }
}