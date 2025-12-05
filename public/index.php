<?php
// Страница управления
?>
<!DOCTYPE html>
<html lang="ru">
	<head>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title>Управление AmoCRM интеграцией</title>
		<link rel="stylesheet" href="/public/assets/css/index.css">
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
					<h2>
						<i class="fas fa-exchange-alt"></i> Задача 2.1: Перемещение сделок
					</h2>
					<p>
						Находит сделки на этапе "Заявка" с бюджетом > 5000 и перемещает их
						на этап "Ожидание клиента".
					</p>

					<div class="requirements">
						<h4><i class="fas fa-filter"></i> Критерии:</h4>
						<ul>
							<li>Стадия: <strong>Заявка</strong></li>
							<li>Бюджет: <strong>> 5000</strong></li>
							<li>
								Перемещение на: <strong>Ожидание клиента</strong>
							</li>
						</ul>
					</div>

					<button class="btn" data-action="move-leads">
    				<i class="fas fa-play"></i> Запустить перемещение
					</button>
				</div>

				<div class="card">
					<h2><i class="fas fa-copy"></i> Задача 2.2: Копирование сделок</h2>
					<p>
						Находит сделки на этапе "Клиент подтвердил" с бюджетом = 4999,
						создает их копии со всеми примечаниями и задачами.
					</p>

					<div class="requirements">
						<h4><i class="fas fa-filter"></i> Критерии:</h4>
						<ul>
							<li>
								Стадия: <strong>Клиент подтвердил</strong>
							</li>
							<li>Бюджет: <strong>= 4999</strong> (точно)</li>
							<li>
								Копия создается на: <strong>Ожидание клиента</strong>
							</li>
							<li>Копируются: примечания и задачи</li>
						</ul>
					</div>

					<button class="btn btn-danger" data-action="copy-leads">
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
				<p>
					<i class="fas fa-info-circle"></i> Воронка: "Воронка" (ID: 10362662) |
					Последнее обновление:
					<?php echo date('d.m.Y H:i:s'); ?>
				</p>
			</div>
		</div>

		<script src="assets/js/index.js"></script>
	</body>
</html>