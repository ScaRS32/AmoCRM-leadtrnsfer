<?php

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
            $expires_in = json_decode(file_get_contents("TOKEN.txt"))->{'expires_in'};
            if($expires_in < time()) {
                $this->access_token = json_decode(file_get_contents("TOKEN.txt"))->{'access_token'};
                $this->GetToken(true);
            }
            else
                $this->access_token = json_decode(file_get_contents("TOKEN.txt"))->{'access_token'};
        }
        else
            $this->GetToken();
    }

    function GetToken($refresh = false){
        $link = 'https://' . $this->subDomain . '.amocrm.ru/oauth2/access_token'; //Формируем URL для запроса

        /** Соберем данные для запроса */
        if($refresh)
        {
            $data = [
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type' => 'refresh_token',
                'refresh_token' => json_decode(file_get_contents("TOKEN.txt"))->{'refresh_token'},
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

        $curl = curl_init(); //Сохраняем дескриптор сеанса cURL
        /** Устанавливаем необходимые опции для сеанса cURL  */
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json']);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        /** Теперь мы можем обработать ответ, полученный от сервера. Это пример. Вы можете обработать данные своим способом. */
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

        try
        {
            /** Если код ответа не успешный - возвращаем сообщение об ошибке  */
            if ($code < 200 || $code > 204) {
                throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undefined error', $code);
            }
        }
        catch(\Exception $e)
        {
            echo $out;
            die('Ошибка: ' . $e->getMessage() . PHP_EOL . 'Код ошибки: ' . $e->getCode());
        }

        /**
         * Данные получаем в формате JSON, поэтому, для получения читаемых данных,
         * нам придётся перевести ответ в формат, понятный PHP
         */
        $response = json_decode($out, true);

        $this->access_token = $response['access_token'];

        $token = [
            'access_token' => $response['access_token'], //Access токен
            'refresh_token' => $response['refresh_token'], //Refresh токен
            'token_type' => $response['token_type'], //Тип токена
            'expires_in' => time() + $response['expires_in'] //Через сколько действие токена истекает
        ];

        file_put_contents("TOKEN.txt", json_encode($token));
    }

    function CurlRequest($link, $method, $PostFields = [])
    {
        /** Формируем заголовки */
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
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        /* Теперь мы можем обработать ответ, полученный от сервера. Это пример. Вы можете обработать данные своим способом. */
        $code = (int) $code;
        $errors = array(
            301 => 'Moved permanently',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        );

        try
        {
            #Если код ответа не равен 200 или 204 - возвращаем сообщение об ошибке
            if ($code != 200 && $code != 204) {
                throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error', $code);
            }

        } catch (Exception $E) {
            $this->Error('Ошибка: ' . $E->getMessage() . PHP_EOL . 'Код ошибки: ' . $E->getCode() . $link);
        }


        return $out;
    }

    function GETRequestApi($service, $params = [])
    {
        $result = '';
        try {
            $url = "";
            if ($params !== []) {
                $params = ToGetArray($params);
                $url = 'https://' . $this->subDomain . '.amocrm.ru/api/v4/' . $service . '?' . $params;
            } else
                $url = 'https://' . $this->subDomain . '.amocrm.ru/api/v4/' . $service;

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

    function Error($e){
        file_put_contents("ERROR_LOG.txt", $e);
    }
}

function ToGetArray($array){
    $result = "";

    foreach ($array as $key => $value)
    {
        $result .= $key . "=" . $value . '&';
    }

    return substr($result,0,-1);
}