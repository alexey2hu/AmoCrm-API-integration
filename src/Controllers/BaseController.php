<?php

declare(strict_types=1);

namespace App\Controllers;

abstract class BaseController
{
    protected function sendJsonResponse(array $response): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    protected function sendErrorResponse(\Throwable $e, int $code = 500): void
    {
        http_response_code($code);
        
        $this->sendJsonResponse([
            'success' => false,
            'message' => 'Internal Server Error: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s'),
            'data' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $this->getShortTrace($e)
            ]
        ]);
    }
    
    protected function getShortTrace(\Throwable $e): string
    {
        // Возвращаем только первые 5 строк трейса для безопасности
        $trace = explode("\n", $e->getTraceAsString());
        return implode("\n", array_slice($trace, 0, 5));
    }
}