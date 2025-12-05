<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Handlers\MoveLeadsHandler;

class MoveLeads extends BaseController
{
    /**
     * Основной метод обработки запроса перемещения лидов
     * Отлавливает все исключения и возвращает JSON-ответ
     */
    public function handle(): void
    {
        try {
            // Создаем обработчик для перемещения лидов
            $handler = new MoveLeadsHandler();
            
            // Выполняем основную логику перемещения
            $result = $handler->handle();
            
            // Отправляем успешный JSON-ответ с результатом
            $this->sendJsonResponse($result);
            
        } catch (\Throwable $e) {
            // Перехватываем любые исключения и отправляем ошибку
            $this->sendErrorResponse($e);
        }
    }
}