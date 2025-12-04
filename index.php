<?php
// index.php - ИСПРАВЛЕННАЯ версия
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
require_once __DIR__ . '/src/Controllers/CopyLeads.php';
require_once __DIR__ . '/src/Handlers/MoveLeadsHandler.php';
require_once __DIR__ . '/src/Handlers/CopyLeadsHandler.php';

// Определяем какой эндпоинт вызывать
$action = $_GET['action'] ?? 'move-leads'; // по умолчанию 2.1

// Для отладки можно добавить логирование
error_log("Index.php received action: " . $action);

try {
    switch ($action) {
        case 'move-leads': // 2.1 - Перемещение сделок
            error_log("Executing MoveLeads controller");
            $controller = new App\Controllers\MoveLeads();
            $controller->handle();
            break;
            
        case 'copy-leads': // 2.2 - Копирование сделок
            error_log("Executing CopyLeads controller");
            $controller = new App\Controllers\CopyLeads();
            $controller->handle();
            break;
            
        default:
            error_log("Unknown action, defaulting to MoveLeads");
            $controller = new App\Controllers\MoveLeads();
            $controller->handle();
            break;
    }
    
} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $ex->getMessage(),
        'file' => $ex->getFile(),
        'line' => $ex->getLine(),
        'timestamp' => date('Y-m-d H:i:s'),
        'action_received' => $action // Добавляем для отладки
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    error_log("[" . date('Y-m-d H:i:s') . "] Action: {$action}, Error: " . $ex->getMessage() . " in " . $ex->getFile() . ":" . $ex->getLine());
}