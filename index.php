<?php
declare(strict_types=1);

// ========== ВАРИАНТ B: С КОНТРОЛЛЕРОМ MoveLeads ==========

// 1. CORS заголовки ДЛЯ ВСЕХ ЗАПРОСОВ
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, *");

// 2. Preflight OPTIONS запросы
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// 3. ЗАГРУЗКА ВСЕХ НЕОБХОДИМЫХ КЛАССОВ
// Вариант БЕЗ Composer (загружаем вручную):
require_once __DIR__ . '/src/Clients/AmoCrmV4Client.php';
require_once __DIR__ . '/src/Controllers/MoveLeads.php';
require_once __DIR__ . '/src/Handlers/MoveLeadsHandler.php';

// Вариант С Composer (раскомментировать и удалить строки выше):
// require_once __DIR__ . '/vendor/autoload.php';

// 4. Загрузка конфигурации
$data = require_once __DIR__ . '/src/Config/data.php';

// 5. Импорт классов (если используете автозагрузчик)
// use App\Controllers\MoveLeads;

try {
    echo "=== Начинаем работу ===\n";
    
    // Проверка что классы загружены
    if (class_exists('MoveLeads') || class_exists('App\Controllers\MoveLeads')) {
        echo "✅ Контроллер MoveLeads загружен\n";
    } else {
        throw new Exception("Контроллер MoveLeads не найден!");
    }
    
    if (class_exists('MoveLeadsHandler') || class_exists('App\Handlers\MoveLeadsHandler')) {
        echo "✅ Handler MoveLeadsHandler загружен\n";
    } else {
        throw new Exception("Handler MoveLeadsHandler не найден!");
    }
    
    // СОЗДАНИЕ И ЗАПУСК КОНТРОЛЛЕРА
    echo "\n=== Запускаем контроллер ===\n";
    
    // Способ 1: Если классы с namespace
    // $controller = new App\Controllers\MoveLeads();
    
    // Способ 2: Если классы БЕЗ namespace (просто MoveLeads)
    $controller = new App\Controllers\MoveLeads();
    
    $controller->handle();
    
    echo "\n✅ Контроллер завершил работу\n";

} catch (Exception $ex) {
    // Контроллер должен сам возвращать JSON ошибки,
    // но на всякий случай добавляем fallback
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $ex->getMessage(),
        'file' => $ex->getFile(),
        'line' => $ex->getLine()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    file_put_contents("ERROR_LOG.txt", 
        date('Y-m-d H:i:s') . ' - ' . $ex->getMessage() . 
        ' in ' . $ex->getFile() . ':' . $ex->getLine() . PHP_EOL, 
        FILE_APPEND
    );
}