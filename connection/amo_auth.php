<?php

function authorizeAmoCRM() {
    require_once 'config.php';

    $data = [
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'grant_type'    => 'authorization_code',
        'code'          => $authorization_code,
        'redirect_uri'  => $redirect_uri,
    ];

    $link = "https://{$subdomain}.amocrm.ru/oauth2/access_token";
    $responseFilePath = __DIR__ . '/response_amo.json';

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
    curl_setopt($curl, CURLOPT_URL, $link);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);


    $out = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);

    curl_close($curl);

    if ($curl_error) {
        file_put_contents($responseFilePath, json_encode(["error" => $curl_error, "details" => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        throw new Exception("Ошибка cURL: $curl_error. Подробности ответа: $out");
    }

    if ($code !== 200) {
        $response = json_decode($out, true);
        file_put_contents($responseFilePath, json_encode(["error" => "HTTP код $code", "response" => $response], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        throw new Exception("Ошибка авторизации: HTTP код $code. Ответ сервера: $out");
    }

    $response = json_decode($out, true);
    file_put_contents($responseFilePath, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return [
        "access_token"  => $response['access_token'],
        "refresh_token" => $response['refresh_token'],
        "token_type"    => $response['token_type'],
        "expires_in"    => $response['expires_in'],
        "endTokenTime"  => $response['expires_in'] + time(),
    ];
}

function getAccessToken() {
    $tokensFilePath = __DIR__ . '/tokens.json';  // Путь к файлу с токенами
    $responseFilePath = __DIR__ . '/response_amo.json';  // Путь к файлу с ответами

    if (!file_exists($tokensFilePath)) {
        file_put_contents($tokensFilePath, json_encode([]));
    }

    $fileContent = file_get_contents($tokensFilePath);
    $tokens = json_decode($fileContent, true);

    if (empty($tokens) || !isset($tokens['access_token'])) {
        $tokens = authorizeAmoCRM();
        file_put_contents($tokensFilePath, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    if (time() >= $tokens['endTokenTime']) {
        $tokens = refreshToken($tokens);
        file_put_contents($tokensFilePath, json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    return $tokens;
}

function refreshToken($tokens) {
    require_once 'config.php';

    if (empty($tokens['refresh_token'])) {
        throw new Exception("Обновление токена невозможно, refresh_token отсутствует.");
    }

    $data = [
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'grant_type'    => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'redirect_uri'  => $redirect_uri,
    ];

    $link = "https://{$subdomain}.amocrm.ru/oauth2/access_token";

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-oAuth-client/1.0');
    curl_setopt($curl, CURLOPT_URL, $link);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

    $out = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);

    curl_close($curl);

    if ($curl_error) {
        throw new Exception("Ошибка cURL при обновлении токенов: $curl_error. Ответ сервера: $out");
    }

    if ($code !== 200) {
        throw new Exception("Ошибка обновления токенов: HTTP код $code. Ответ сервера: $out");
    }

    $response = json_decode($out, true);

    return [
        "access_token"  => $response['access_token'],
        "refresh_token" => $response['refresh_token'],
        "token_type"    => $response['token_type'],
        "expires_in"    => $response['expires_in'],
        "endTokenTime"  => $response['expires_in'] + time(),
    ];
}

