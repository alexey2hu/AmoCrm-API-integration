<?php
// Страница управления
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление AmoCRM интеграцией</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .controls {
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 25px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            border-color: #4f46e5;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.1);
        }
        
        .card h2 {
            color: #1e293b;
            margin-bottom: 15px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card h2 i {
            font-size: 1.3rem;
        }
        
        .card p {
            color: #64748b;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .requirements {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .requirements h4 {
            color: #475569;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .requirements ul {
            padding-left: 20px;
            color: #64748b;
        }
        
        .requirements li {
            margin-bottom: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            width: 100%;
        }
        
        .btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        .btn-danger:hover {
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
        }
        
        .console {
            margin: 0 30px 30px;
            background: #1e293b;
            color: #f1f5f9;
            border-radius: 12px;
            overflow: hidden;
            height: 400px;
            display: flex;
            flex-direction: column;
        }
        
        .console-header {
            background: #0f172a;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #334155;
        }
        
        .console-header h3 {
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .console-controls {
            display: flex;
            gap: 10px;
        }
        
        .console-btn {
            padding: 6px 12px;
            background: #334155;
            color: #cbd5e1;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .console-btn:hover {
            background: #475569;
        }
        
        .console-output {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .console-output .success {
            color: #10b981;
        }
        
        .console-output .error {
            color: #ef4444;
        }
        
        .console-output .info {
            color: #3b82f6;
        }
        
        .console-output .warning {
            color: #f59e0b;
        }
        
        .timestamp {
            color: #94a3b8;
            font-size: 0.8rem;
            margin-right: 10px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #3b82f6;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #4f46e5;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 0.9rem;
            border-top: 1px solid #e2e8f0;
        }
        
        @media (max-width: 768px) {
            .controls {
                grid-template-columns: 1fr;
            }
            
            .console {
                margin: 0 20px 20px;
                height: 300px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-cogs"></i> Управление AmoCRM интеграцией</h1>
            <p>Автоматизация обработки сделок в воронке "Воронка"</p>
        </div>
        
        <div class="controls">
            <div class="card">
                <h2><i class="fas fa-exchange-alt"></i> Задача 2.1: Перемещение сделок</h2>
                <p>Находит сделки на этапе "Заявка" с бюджетом > 5000 и перемещает их на этап "Ожидание клиента".</p>
                
                <div class="requirements">
                    <h4><i class="fas fa-filter"></i> Критерии:</h4>
                    <ul>
                        <li>Стадия: <strong>Заявка</strong> (ID: 81971238)</li>
                        <li>Бюджет: <strong>> 5000</strong></li>
                        <li>Перемещение на: <strong>Ожидание клиента</strong> (ID: 81971194)</li>
                    </ul>
                </div>
                
                <button class="btn" onclick="executeAction('move-leads')">
                    <i class="fas fa-play"></i> Запустить перемещение
                </button>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-copy"></i> Задача 2.2: Копирование сделок</h2>
                <p>Находит сделки на этапе "Клиент подтвердил" с бюджетом = 4999, создает их копии со всеми примечаниями и задачами.</p>
                
                <div class="requirements">
                    <h4><i class="fas fa-filter"></i> Критерии:</h4>
                    <ul>
                        <li>Стадия: <strong>Клиент подтвердил</strong> (ID: <span id="confirmed-stage-id">?</span>)</li>
                        <li>Бюджет: <strong>= 4999</strong> (точно)</li>
                        <li>Копия создается на: <strong>Ожидание клиента</strong> (ID: 81971194)</li>
                        <li>Копируются: примечания и задачи</li>
                    </ul>
                </div>
                
                <button class="btn btn-danger" onclick="executeAction('copy-leads')">
                    <i class="fas fa-copy"></i> Запустить копирование
                </button>
            </div>
        </div>
        
        <div class="console">
            <div class="console-header">
                <h3><i class="fas fa-terminal"></i> Консоль выполнения</h3>
                <div class="console-controls">
                    <button class="console-btn" onclick="clearConsole()">
                        <i class="fas fa-trash"></i> Очистить
                    </button>
                    <button class="console-btn" onclick="copyConsole()">
                        <i class="fas fa-copy"></i> Копировать
                    </button>
                </div>
            </div>
            
            <div class="console-output" id="console-output">
                <div class="info">
                    <span class="timestamp"><?php echo date('H:i:s'); ?></span>
                    Готов к работе. Выберите действие выше.
                </div>
            </div>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                Выполнение запроса...
            </div>
        </div>
        
        <div class="footer">
            <p><i class="fas fa-info-circle"></i> Воронка: "Воронка" (ID: 10362662) | Последнее обновление: <?php echo date('d.m.Y H:i:s'); ?></p>
        </div>
    </div>
    
    <script>
        // Определяем ID стадии "Клиент подтвердил" (если он есть в конфиге)
        document.getElementById('confirmed-stage-id').textContent = '?';
        
        // Функция выполнения действия
        function executeAction(action) {
            const consoleOutput = document.getElementById('console-output');
            const loading = document.getElementById('loading');
            
            // Показываем загрузку
            loading.classList.add('active');
            
            // Добавляем запись в консоль
            const timestamp = new Date().toLocaleTimeString();
            let actionName = '';
            let actionDescription = '';
            
            if (action === 'move-leads') {
                actionName = 'Перемещение сделок';
                actionDescription = 'Поиск сделок на стадии "Заявка" с бюджетом > 5000';
            } else {
                actionName = 'Копирование сделок';
                actionDescription = 'Поиск сделок на стадии "Клиент подтвердил" с бюджетом = 4999';
            }
            
            consoleOutput.innerHTML += `
                <div class="info">
                    <span class="timestamp">${timestamp}</span>
                    <strong>${actionName}</strong>: ${actionDescription}
                </div>
            `;
            
            // Прокручиваем консоль вниз
            consoleOutput.scrollTop = consoleOutput.scrollHeight;
            
            // Выполняем AJAX запрос
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `index.php?action=${action}&timestamp=${Date.now()}`, true);
            
            xhr.onload = function() {
                loading.classList.remove('active');
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        const timestamp = new Date().toLocaleTimeString();
                        
                        if (response.success) {
                            consoleOutput.innerHTML += `
                                <div class="success">
                                    <span class="timestamp">${timestamp}</span>
                                    ✅ ${response.message}
                                </div>
                            `;
                            
                            // Выводим детали
                            if (response.data) {
                                let details = '';
                                
                                if (action === 'move-leads') {
                                    details = `
                                        Найдено сделок: ${response.data.total_leads_on_application_stage || response.data.total_leads || 0}<br>
                                        С бюджетом > 5000: ${response.data.leads_with_budget_over_threshold || response.data.filtered_leads || 0}<br>
                                        Успешно перемещено: ${response.data.successfully_moved || response.data.moved_count || 0}<br>
                                        Не удалось переместить: ${response.data.failed_to_move || response.data.failed_count || 0}
                                    `;
                                } else {
                                    details = `
                                        Найдено сделок: ${response.data.total_leads_found || 0}<br>
                                        Успешно скопировано: ${response.data.successfully_copied || 0}<br>
                                        Не удалось скопировать: ${response.data.failed_to_copy || 0}
                                    `;
                                }
                                
                                consoleOutput.innerHTML += `
                                    <div class="info">
                                        <span class="timestamp">${timestamp}</span>
                                        📊 Результаты:<br>
                                        ${details}
                                    </div>
                                `;
                            }
                        } else {
                            consoleOutput.innerHTML += `
                                <div class="error">
                                    <span class="timestamp">${timestamp}</span>
                                    ❌ ${response.message}
                                </div>
                            `;
                        }
                    } catch (e) {
                        consoleOutput.innerHTML += `
                            <div class="error">
                                <span class="timestamp">${timestamp}</span>
                                ❌ Ошибка парсинга ответа: ${e.message}
                            </div>
                        `;
                    }
                } else {
                    consoleOutput.innerHTML += `
                        <div class="error">
                            <span class="timestamp">${new Date().toLocaleTimeString()}</span>
                            ❌ Ошибка HTTP: ${xhr.status} ${xhr.statusText}
                        </div>
                    `;
                }
                
                // Прокручиваем консоль вниз
                consoleOutput.scrollTop = consoleOutput.scrollHeight;
            };
            
            xhr.onerror = function() {
                loading.classList.remove('active');
                consoleOutput.innerHTML += `
                    <div class="error">
                        <span class="timestamp">${new Date().toLocaleTimeString()}</span>
                        ❌ Ошибка сети при выполнении запроса
                    </div>
                `;
                consoleOutput.scrollTop = consoleOutput.scrollHeight;
            };
            
            xhr.send();
        }
        
        // Функция очистки консоли
        function clearConsole() {
            const consoleOutput = document.getElementById('console-output');
            const timestamp = new Date().toLocaleTimeString();
            
            consoleOutput.innerHTML = `
                <div class="info">
                    <span class="timestamp">${timestamp}</span>
                    Консоль очищена. Готов к работе.
                </div>
            `;
        }
        
        // Функция копирования содержимого консоли
        function copyConsole() {
            const consoleOutput = document.getElementById('console-output');
            const text = consoleOutput.innerText;
            
            navigator.clipboard.writeText(text).then(() => {
                const timestamp = new Date().toLocaleTimeString();
                consoleOutput.innerHTML += `
                    <div class="success">
                        <span class="timestamp">${timestamp}</span>
                        📋 Содержимое консоли скопировано в буфер обмена
                    </div>
                `;
                consoleOutput.scrollTop = consoleOutput.scrollHeight;
            });
        }
        
        // Загружаем информацию о конфигурации
        window.addEventListener('load', function() {
            // Можно добавить запрос для получения текущей конфигурации
            // Например, получить ID стадии "Клиент подтвердил" из конфига
            fetch('get_config_info.php')
                .then(response => response.json())
                .then(data => {
                    if (data.client_confirmed_stage_id) {
                        document.getElementById('confirmed-stage-id').textContent = data.client_confirmed_stage_id;
                    }
                })
                .catch(error => {
                    console.log('Не удалось загрузить конфигурацию');
                });
        });
    </script>
    
    <?php
    // Создаем вспомогательный файл для получения информации о конфиге
    $configFile = __DIR__ . '/src/Config/data.php';
    if (!file_exists('get_config_info.php') && file_exists($configFile)) {
        file_put_contents('get_config_info.php', '<?php
        $config = require __DIR__ . \'/src/Config/data.php\';
        header(\'Content-Type: application/json\');
        echo json_encode([
            \'pipeline_id\' => $config[\'pipeline_id\'] ?? null,
            \'application_stage_id\' => $config[\'application_stage_id\'] ?? null,
            \'waiting_stage_id\' => $config[\'waiting_stage_id\'] ?? null,
            \'client_confirmed_stage_id\' => $config[\'client_confirmed_stage_id\'] ?? null,
            \'budget_threshold\' => $config[\'budget_threshold\'] ?? 5000,
            \'copy_budget_value\' => $config[\'copy_budget_value\'] ?? 4999
        ], JSON_UNESCAPED_UNICODE);
        ?>');
    }
    ?>
</body>
</html>