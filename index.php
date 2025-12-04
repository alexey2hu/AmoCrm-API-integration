<?php

declare(strict_types=1);

// Загрузка конфигурации
$data = require_once __DIR__ . '/src/Config/data.php';
// Все для подключения к AmoCrm
require_once __DIR__ . '/src/Clients/AmoCrmV4Client.php';

try {
    $amoV4Client = new AmoCrmV4Client(
        $data['sub_domain'],
        $data['client_id'],
        $data['client_secret'],
        $data['code'],
        $data['redirect_url']
    );

    // Пример получения всех сделок
    $leads_client_confirm = $amoV4Client->GETAll('leads');
    print_r($leads_client_confirm);

    /*
    $leads_client_confirm = $amoV4Client->GETAll("leads", [
        "filter[statuses][0][pipeline_id]" => ,
        "filter[statuses][0][status_id]" =>
    ]);
    */

} catch (Exception $ex) {
    var_dump($ex);
    file_put_contents("ERROR_LOG.txt", 'Ошибка: ' . $ex->getMessage() . PHP_EOL . 'Код ошибки:' . $ex->getCode());
}

/*
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: *");
    exit(0);
}
*/

/*
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-type");
header("Access-Control-Allow-Headers: *");

require_once __DIR__ . '/vendor/autoload.php';
*/

/*
use App\Controllers\MoveLeads;

$controller = new MoveLeads();
$controller->handle();
*/