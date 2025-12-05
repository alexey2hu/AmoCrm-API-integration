<?php

declare(strict_types=1);

namespace App\Controllers;

abstract class BaseController
{
    /**
     * Отправляет JSON-ответ клиенту
     */
    protected function sendJsonResponse(array $response): void
    {
        header('Content-Type: application/json; charset=utf-8');
        // JSON_PRETTY_PRINT - читаемый формат, JSON_UNESCAPED_UNICODE - сохраняем кириллицу
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Отправляет JSON-ответ с ошибкой
     * По умолчанию код ответа 500 (Internal Server Error)
     */
    protected function sendErrorResponse(\Throwable $e, int $code = 500): void
    {
        http_response_code($code);
        
        $this->sendJsonResponse([
            'success' => false,
            'message' => 'Internal Server Error: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => [
                'file' => $e->getFile(),        // Файл, где произошла ошибка
                'line' => $e->getLine(),        // Строка с ошибкой
                'trace' => $this->getShortTrace($e) // Сокращенный стек вызовов
            ]
        ]);
    }
    
    /**
     * Возвращает только первые 5 строк трейса в целях безопасности
     * (чтобы не показывать клиенту полную структуру приложения)
     */
    protected function getShortTrace(\Throwable $e): string
    {
        $trace = explode("\n", $e->getTraceAsString());
        return implode("\n", array_slice($trace, 0, 5));
    }
}