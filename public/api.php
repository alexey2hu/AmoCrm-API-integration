<?php
// api.php
declare(strict_types=1);

// Автозагрузка Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Инициализируем конфигурацию
App\Config\Config::load();

// CORS заголовки
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Определяем какой эндпоинт вызывать
$action = $_GET['action'] ?? 'move-leads';

error_log("Index.php received action: " . $action);

try {
    switch ($action) {
        case 'copy-leads':
            error_log("Executing CopyLeads controller");
            $controller = new App\Controllers\CopyLeads();
            $controller->handle();
            break;
            
        case 'move-leads':
            error_log("Executing MoveLeads controller");
            $controller = new App\Controllers\MoveLeads();
            $controller->handle();
            break;
            
        default:
            // Выводим оба результата друг за другом
            error_log("Executing both controllers sequentially");
            
            // 1. Копирование
            $controller = new App\Controllers\CopyLeads();
            $controller->handle();
            echo "\n\n"; // Разделитель
            
            // 2. Перемещение
            $controller = new App\Controllers\MoveLeads();
            $controller->handle();
            break;
    }
    
} catch (Exception $ex) {
    header('Content-Type: application/json');
    http_response_code(500);
    
    echo json_encode([
        'error' => $ex->getMessage(),
        'file' => $ex->getFile(),
        'line' => $ex->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    error_log("[" . date('Y-m-d H:i:s') . "] Action: {$action}, Error: " . $ex->getMessage() . " in " . $ex->getFile() . ":" . $ex->getLine());
}