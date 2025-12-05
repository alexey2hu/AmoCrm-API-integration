<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Handlers\CopyLeadsHandler;

class CopyLeads extends BaseController
{
    /**
     * Основной метод обработки запроса копирования лидов
     * Отлавливает все исключения и возвращает JSON-ответ
     */
    public function handle(): void
    {
        try {
            // Создаем обработчик для копирования лидов
            $handler = new CopyLeadsHandler();
            
            // Выполняем основную логику копирования
            $result = $handler->handle();
            
            // Отправляем успешный JSON-ответ с результатом
            $this->sendJsonResponse($result);
            
        } catch (\Throwable $e) {
            // Перехватываем любые исключения и отправляем ошибку
            $this->sendErrorResponse($e);
        }
    }
}