<?php

namespace App\Clients;

use Exception;
use ErrorException;

class AmoCrmV4Client
{
    var $curl = null;
    var $subDomain = ""; #Наш аккаунт - поддомен

    var $client_id = "";
    var $client_secret = "";
    var $code = "";
    var $redirect_uri = "";

    var $access_token = "";

    var $token_file = "TOKEN.txt";

    function __construct($subDomain, $client_id, $client_secret, $code, $redirect_uri)
    {
        $this->subDomain = $subDomain;
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->code = $code;
        $this->redirect_uri = $redirect_uri;
        
        if(file_exists($this->token_file)) {
            $tokenData = json_decode(file_get_contents("TOKEN.txt"), true);
            if($tokenData['expires_in'] < time()) {
                $this->GetToken(true);
            } else {
                $this->access_token = $tokenData['access_token'];
            }
        } else {
            $this->GetToken();
        }
    }

    function GetToken($refresh = false){
        $link = 'https://' . $this->subDomain . '.amocrm.ru/oauth2/access_token';

        /** Соберем данные для запроса */
        if($refresh) {
            $tokenData = json_decode(file_get_contents("TOKEN.txt"), true);
            $data = [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $tokenData['refresh_token'],
                'redirect_uri' => $this->redirect_uri
            ];
        } else {
            $data = [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'authorization_code',
                'code' => $this->code,
                'redirect_uri' => $this->redirect_uri
            ];
        }

        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json']);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 0);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $code = (int)$code;
        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        try {
            if ($code < 200 || $code > 204) {
                throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
            }
        } catch(Exception $e) {
            echo $out;
            die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
        }

        $response = json_decode($out, true);

        $this->access_token = $response['access_token'];

        $token = [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'],
            'token_type' => $response['token_type'],
            'expires_in' => time() + $response['expires_in']
        ];

        file_put_contents("TOKEN.txt", json_encode($token));
    }

    function CurlRequest($link, $method, $PostFields = [])
    {
        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ];

        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        if ($method == "POST" || $method == 'PATCH') {
            curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($PostFields));
        }
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,$method);
        curl_setopt($curl,CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 0);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        $code = (int) $code;
        $errors = [
            301 => 'Moved permanently',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        try {
            if ($code != 200 && $code != 204) {
                throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error', $code);
            }
        } catch (Exception $E) {
            $this->Error('Ошибка: ' . $E->getMessage() . PHP_EOL . 'Код ошибки: ' . $E->getCode() . ' URL: ' . $link);
        }

        return $out;
    }

    function GETRequestApi($service, $params = [])
    {
        $result = '';
        try {
            if (!empty($params)) {
                // Используем http_build_query для корректного формирования URL
                $queryString = http_build_query($params);
                $url = 'https://' . $this->subDomain . '.amocrm.ru/api/v4/' . $service . '?' . $queryString;
            } else {
                $url = 'https://' . $this->subDomain . '.amocrm.ru/api/v4/' . $service;
            }

            $result = json_decode($this->CurlRequest($url, 'GET'), true);
            usleep(250000);

        } catch (ErrorException $e) {
            $this->Error($e);
        }

        return $result;
    }

    function POSTRequestApi($service, $params = [], $method = "POST")
    {   
        $result = '';
        try {
            $url = 'https://' . $this->subDomain . '.amocrm.ru/api/v4/' . $service;
            $result = json_decode($this->CurlRequest($url, $method, $params), true);
            usleep(250000);
        } catch (ErrorException $e) {
            $this->Error($e);
        }

        return $result;
    }

    function GETAll($entity, $custom_params = null){
        $array = [];
        $i = 1;

        if ($entity == 'leads') {
            $with = 'contacts';
        } else if ($entity == 'contacts') {
            $with = 'leads';
        } else {
            $with = 'leads,contacts';
        }

        $params = [
            'limit' => 250,
            'with' => $with
        ];

        if ($custom_params !== null) {
            $params = array_merge($params, $custom_params);
        }

        do {
            $params['page'] = $i;
            $response = $this->GETRequestApi($entity, $params);
            $array_temp = $response['_embedded'][$entity] ?? null;
            
            if ($array_temp === null) {
                break;
            }
            
            foreach ($array_temp as $elem) {
                $array[] = $elem;
            }
            $i++;
        } while (!empty($array_temp));

        return $array;
    }

    function GET($entity, $id = null, $params = []) {
        $service = $entity;
        if ($id !== null) {
            $service .= '/' . $id;
        }
        return $this->GETRequestApi($service, $params);
    }

    function POST($entity, $data = []) {
        return $this->POSTRequestApi($entity, $data, 'POST');
    }

    function PATCH($entity, $data = []) {
        return $this->POSTRequestApi($entity, $data, 'PATCH');
    }

    function DELETE($entity, $id = null) {
        $service = $entity;
        if ($id !== null) {
            $service .= '/' . $id;
        }
        $url = 'https://' . $this->subDomain . '.amocrm.ru/api/v4/' . $service;
        return json_decode($this->CurlRequest($url, 'DELETE'), true);
    }

    function Error($e){
        $logMessage = date('Y-m-d H:i:s') . ' - ' . $e . PHP_EOL;
        file_put_contents("ERROR_LOG.txt", $logMessage, FILE_APPEND);
        error_log($logMessage);
    }
}