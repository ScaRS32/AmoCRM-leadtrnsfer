<?php

require_once __DIR__ . '/src/AmoCrmV4Client.php';

define('SUB_DOMAIN', '****');
define('CLIENT_ID', '********');
define('CLIENT_SECRET', '***********');
define('CODE', '*************');
define('REDIRECT_URL', '**************');

echo "<pre>";

try {
    $amoV4Client = new AmoCrmV4Client(SUB_DOMAIN, CLIENT_ID, CLIENT_SECRET, CODE, REDIRECT_URL);
    //получение сделок по фильтру воронки и статуса
    $result = $amoV4Client->GETRequestApi('leads',[
        'filter[statuses][0][pipeline_id]'=>3336403,
        'filter[statuses][0][status_id]'=>36409435
    ])['_embedded']['leads'];
    //создаём отдельный массив и запускаем проверку на цену больше  4999
    $highPriceLeads = array();
    foreach ($result as $lead) {
        if (isset($lead['price']) && $lead['price'] >= 4999) {
            $highPriceLeads[] = $lead;
        }
    }
// вывод цен наверх
    foreach ($highPriceLeads as $lead) {
        echo $lead['price'] . ' ';
    }
    //для всех сделок подходящих по предыдущ. функции - перенос на новый статус
        $leadsData = array();
        foreach ($highPriceLeads as $lead) {
            $leadsData[] = array(
                'id' => $lead['id'],
                'status_id' => 123456); //Вписываете id нужного статуса
            $amoV4Client->POSTRequestApi('leads', $leadsData, 'PATCH');
        }
    var_dump($leadsData);
}
catch (Exception $ex) {
    var_dump($ex);
    json_decode($ex);
    file_put_contents("ErrLog.txt", 'Ошибка: ' . $ex->getMessage() . PHP_EOL . 'Код ошибки: ' . $ex->getCode());
}