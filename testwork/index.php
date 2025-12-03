<?php
require_once __DIR__ . '/src/AmoCrmV4Client.php';
$config = require_once __DIR__ . '/config.php';

echo "<pre>";

try {
    $amoV4Client = new AmoCrmV4Client(
        $config['sub_domain'],
        $config['client_id'],
        $config['client_secret'],
        $config['code'],
        $config['redirect_url']
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



