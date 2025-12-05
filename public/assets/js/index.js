// js/index.js

let isProcessing = false

// Функция для выполнения действия
function executeAction(action) {
	// Проверка на уже выполняющийся запрос
	if (isProcessing) {
		const timestamp = new Date().toLocaleTimeString()
		addConsoleMessage(
			`warning`,
			`${timestamp} ⚠️ Уже выполняется другая операция. Дождитесь завершения.`
		)
		return
	}

	console.log('Executing action:', action)

	// Показать индикатор загрузки
	const loading = document.getElementById('loading')
	const consoleOutput = document.getElementById('console-output')

	// Отключаем кнопку, которую нажали
	const clickedButton =
		event?.target || document.querySelector(`.btn[data-action="${action}"]`)
	if (clickedButton) {
		clickedButton.disabled = true
		clickedButton.style.opacity = '0.7'
		clickedButton.style.cursor = 'not-allowed'
	}

	loading.classList.add('active')
	isProcessing = true

	// Добавляем сообщение о начале выполнения
	const startTime = new Date()
	const timestamp = startTime.toLocaleTimeString()
	addConsoleMessage(
		`info`,
		`${timestamp} Начало выполнения: ${getActionName(action)}`
	)

	// ПУТЬ К API
	const apiUrl = './api.php'

	// Создаем XMLHttpRequest для более детального контроля
	const xhr = new XMLHttpRequest()
	xhr.open('GET', `${apiUrl}?action=${encodeURIComponent(action)}`, true)
	xhr.setRequestHeader('Accept', 'application/json')

	xhr.onload = function () {
		loading.classList.remove('active')
		isProcessing = false

		// Включаем кнопку обратно
		if (clickedButton) {
			clickedButton.disabled = false
			clickedButton.style.opacity = '1'
			clickedButton.style.cursor = 'pointer'
		}

		const endTime = new Date()
		const executionTime = endTime - startTime
		const timestamp = endTime.toLocaleTimeString()

		if (xhr.status === 200) {
			try {
				const response = JSON.parse(xhr.responseText)
				console.log('API JSON response:', response)

				if (response.success) {
					// Успешный JSON ответ
					addConsoleMessage(
						'success',
						`${timestamp} ✅ ${
							response.message || getActionName(action) + ' выполнено успешно!'
						}`
					)
					addConsoleMessage('info', `   Время выполнения: ${executionTime}ms`)

					// Выводим данные если есть
					if (response.data) {
						outputDataDetails(response.data, action, timestamp)
					}

					// Дополнительные поля
					if (response.total !== undefined) {
						addConsoleMessage('info', `   Всего обработано: ${response.total}`)
					}
					if (response.moved !== undefined) {
						addConsoleMessage('info', `   Перемещено: ${response.moved}`)
					}
					if (response.copied !== undefined) {
						addConsoleMessage('info', `   Скопировано: ${response.copied}`)
					}
					if (response.skipped !== undefined) {
						addConsoleMessage('warning', `   Пропущено: ${response.skipped}`)
					}
				} else {
					// Ошибка в JSON ответе
					addConsoleMessage(
						'error',
						`${timestamp} ❌ ${
							response.message ||
							getActionName(action) + ' завершилось с ошибкой!'
						}`
					)

					if (response.error) {
						addConsoleMessage('error', `   Ошибка: ${response.error}`)
					}
					if (response.file && response.line) {
						addConsoleMessage(
							'error',
							`   Файл: ${response.file}:${response.line}`
						)
					}
				}
			} catch (jsonError) {
				// Ошибка парсинга JSON
				addConsoleMessage(
					'error',
					`${timestamp} ❌ Ошибка обработки JSON ответа от сервера`
				)
				addConsoleMessage('error', `   ${jsonError.message}`)
				addConsoleMessage(
					'info',
					`   Ответ сервера: ${xhr.responseText.substring(0, 200)}...`
				)
			}
		} else {
			// HTTP ошибка
			addConsoleMessage(
				'error',
				`${timestamp} ❌ Ошибка HTTP: ${xhr.status} ${xhr.statusText}`
			)

			// Пытаемся прочитать JSON даже при ошибке
			try {
				const errorResponse = JSON.parse(xhr.responseText)
				if (errorResponse.error) {
					addConsoleMessage('error', `   ${errorResponse.error}`)
				}
			} catch (e) {
				// Не JSON ответ
				if (xhr.responseText) {
					addConsoleMessage(
						'info',
						`   Ответ: ${xhr.responseText.substring(0, 200)}...`
					)
				}
			}
		}

		// Прокручиваем консоль к последнему сообщению
		consoleOutput.scrollTop = consoleOutput.scrollHeight
	}

	xhr.onerror = function () {
		loading.classList.remove('active')
		isProcessing = false

		// Включаем кнопку обратно
		if (clickedButton) {
			clickedButton.disabled = false
			clickedButton.style.opacity = '1'
			clickedButton.style.cursor = 'pointer'
		}

		const timestamp = new Date().toLocaleTimeString()
		addConsoleMessage(
			'error',
			`${timestamp} ❌ Ошибка сети при выполнении запроса`
		)
		addConsoleMessage('error', `   Не удалось подключиться к серверу`)

		// Прокручиваем консоль к последнему сообщению
		consoleOutput.scrollTop = consoleOutput.scrollHeight
	}

	xhr.ontimeout = function () {
		loading.classList.remove('active')
		isProcessing = false

		// Включаем кнопку обратно
		if (clickedButton) {
			clickedButton.disabled = false
			clickedButton.style.opacity = '1'
			clickedButton.style.cursor = 'pointer'
		}

		const timestamp = new Date().toLocaleTimeString()
		addConsoleMessage('error', `${timestamp} ❌ Таймаут запроса (60 секунд)`)
		addConsoleMessage('error', `   Сервер не ответил вовремя`)

		consoleOutput.scrollTop = consoleOutput.scrollHeight
	}

	// Устанавливаем таймаут 60 секунд
	xhr.timeout = 60000

	xhr.send()
}

// Вывод деталей данных
function outputDataDetails(data, action, timestamp) {
	let details = ''

	if (action === 'move-leads') {
		details = `
        Всего сделок на этапе "Заявка": ${data.total_leads || 0}<br>
        С бюджетом > 5000: ${data.filtered_leads || 0}<br>
        Успешно перемещено: ${data.successfully_moved || 0}<br>
        Не удалось переместить: ${data.failed_to_move || 0}
    `
	} else if (action === 'copy-leads') {
		details = `
				Всего сделок на этапе "Клиент подтвердил": ${data.total_leads_found || 0}<br>
        С бюджетом = 4999: ${data.filtered_leads || 0}<br>
        Успешно скопировано: ${data.successfully_copied || 0}<br>
        Не удалось скопировать: ${data.failed_to_copy || 0}
		`
	}

	if (details) {
		addConsoleMessage('info', `${timestamp} 📊 Результаты:<br>${details}`)
	}
}

// Вспомогательная функция для получения имени действия
function getActionName(action) {
	const actionNames = {
		'move-leads': 'Перемещение сделок',
		'copy-leads': 'Копирование сделок',
	}

	return actionNames[action] || action
}

// Функция для добавления сообщения в консоль
function addConsoleMessage(type, message) {
	const consoleOutput = document.getElementById('console-output')
	const messageDiv = document.createElement('div')
	messageDiv.className = type
	messageDiv.innerHTML = message
	consoleOutput.appendChild(messageDiv)
}

// Функция очистки консоли
function clearConsole() {
	const consoleOutput = document.getElementById('console-output')
	consoleOutput.innerHTML = ''

	const timestamp = new Date().toLocaleTimeString()
	addConsoleMessage('info', `${timestamp} Консоль очищена.`)
}

// Функция копирования содержимого консоли
function copyConsole() {
	const consoleOutput = document.getElementById('console-output')
	const text = consoleOutput.innerText

	navigator.clipboard
		.writeText(text)
		.then(() => {
			const timestamp = new Date().toLocaleTimeString()
			addConsoleMessage(
				'info',
				`${timestamp} Содержимое консоли скопировано в буфер обмена.`
			)
		})
		.catch(err => {
			console.error('Ошибка копирования:', err)
			const timestamp = new Date().toLocaleTimeString()
			addConsoleMessage(
				'error',
				`${timestamp} Ошибка при копировании: ${err.message}`
			)
		})
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function () {
	console.log('DOM загружен, инициализация...')

	// Добавляем обработчики для кнопок через data-атрибуты
	const buttons = document.querySelectorAll('.btn[data-action]')
	buttons.forEach(button => {
		button.addEventListener('click', function (event) {
			const action = this.getAttribute('data-action')
			console.log(`Кнопка нажата: ${action}`)
			executeAction(action)
		})
	})

	// Инициализируем timestamp на странице
	const timestamp = new Date().toLocaleTimeString()
	const initMessage = document.querySelector('#console-output .info')
	if (initMessage) {
		initMessage.innerHTML = `<span class="timestamp">${timestamp}</span> Готов к работе. Выберите действие выше.`
	}
})

// Экспортируем функции для глобального доступа
window.executeAction = executeAction
window.clearConsole = clearConsole
window.copyConsole = copyConsole
window.addConsoleMessage = addConsoleMessage
