<?php

require_once __DIR__ . '/src/Clients/AmoCrmV4Client.php';
$config = require __DIR__ . '/src/Config/data.php';

echo "<h1>Поиск стадий в AmoCRM</h1>";

try {
    $amoClient = new \App\Clients\AmoCrmV4Client(
        $config['sub_domain'],
        $config['client_id'],
        $config['client_secret'],
        $config['code'],
        $config['redirect_url']
    );
    
    echo "✅ Подключение успешно<br><br>";
    
    // Получаем конкретную воронку
    $pipeline = $amoClient->GET('leads/pipelines', $config['pipeline_id']);
    
    echo "<h2>Воронка: {$pipeline['name']} (ID: {$pipeline['id']})</h2>";
    
    if (isset($pipeline['_embedded']['statuses'])) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>Название стадии</th><th>Цвет</th><th>Для конфига</th></tr>";
        
        foreach ($pipeline['_embedded']['statuses'] as $stage) {
            $color = $stage['color'] ?? '000000';
            echo "<tr>";
            echo "<td><strong>{$stage['id']}</strong></td>";
            echo "<td>{$stage['name']}</td>";
            echo "<td style='background-color:#{$color};'>#{$color}</td>";
            echo "<td>";
            
            // Предлагаем конфиг в зависимости от названия
            $stageName = strtolower($stage['name']);
            if (strpos($stageName, 'заявка') !== false || strpos($stageName, 'заявк') !== false) {
                echo "<strong>'application_stage_id' => {$stage['id']}, // {$stage['name']}</strong>";
            } elseif (strpos($stageName, 'ожидан') !== false || strpos($stageName, 'wait') !== false) {
                echo "<strong>'waiting_stage_id' => {$stage['id']}, // {$stage['name']}</strong>";
            } else {
                echo "// '{$stage['name']}'";
            }
            
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<br><h3>📋 Пример для data.php:</h3>";
        echo "<pre>'application_stage_id' => [НАЙДЕННЫЙ_ID],   // Заявка<br>";
        echo "'waiting_stage_id' => [НАЙДЕННЫЙ_ID],     // Ожидание клиента</pre>";
        
    } else {
        echo "❌ Нет стадий в этой воронке";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage();
}