<?php
// index.php (упрощенный)
declare(strict_types=1);

// CORS заголовки
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

// Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Загружаем классы
require_once __DIR__ . '/src/Clients/AmoCrmV4Client.php';
require_once __DIR__ . '/src/Controllers/MoveLeads.php';
require_once __DIR__ . '/src/Handlers/MoveLeadsHandler.php';

try {
    // Создаем и запускаем контроллер
    $controller = new App\Controllers\MoveLeads();
    $controller->handle();
    
} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $ex->getMessage(),
        'file' => $ex->getFile(),
        'line' => $ex->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Логируем ошибку
    error_log("[" . date('Y-m-d H:i:s') . "] " . $ex->getMessage() . " in " . $ex->getFile() . ":" . $ex->getLine());
}