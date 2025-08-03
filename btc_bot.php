<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$configFile = __DIR__ . "/config.json";
$offsetFile = __DIR__ . "/last_update_id.txt";
$tokenFile = __DIR__ . "/config_bot.php";

// Подключаем токен и chat ID
if (!file_exists($tokenFile)) {
    die("Файл с токеном и chatId не найден.");
}
include $tokenFile;

// Загружаем конфиг с порогами
if (!file_exists($configFile)) {
    die("Файл конфигурации {$configFile} не найден.");
}
$config = json_decode(file_get_contents($configFile), true);

// Читаем последний update_id, чтобы не слать дубли
$lastUpdateId = 0;
if (file_exists($offsetFile)) {
    $lastUpdateId = (int)file_get_contents($offsetFile);
}

// Функция отправки уведомлений в Telegram
function sendTelegram($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $message];
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

// Обновляем последний обработанный update_id
$urlUpdates = "https://api.telegram.org/bot{$botToken}/getUpdates?offset=" . ($lastUpdateId + 1);
$responseUpdates = file_get_contents($urlUpdates);
$dataUpdates = json_decode($responseUpdates, true);

if (!empty($dataUpdates['result'])) {
    foreach ($dataUpdates['result'] as $update) {
        $lastUpdateId = $update['update_id'];
    }
    file_put_contents($offsetFile, $lastUpdateId);
}

// Получаем цену BTC с KuCoin
$apiUrl = "https://api.kucoin.com/api/v1/market/orderbook/level1?symbol=BTC-USDT";
$response = @file_get_contents($apiUrl);
if ($response === false) {
    die("Ошибка получения цены BTC");
}

$dataPrice = json_decode($response, true);
if (!isset($dataPrice['data']['price'])) {
    die("Ошибка парсинга цены BTC");
}

$price = floatval($dataPrice['data']['price']);

// Проверяем пороги и отправляем уведомления
if ($price < $config['low']) {
    sendTelegram($botToken, $chatId, "Цена BTC опустилась ниже ({$config['low']}$). Текущая цена: {$price}$");
} elseif ($price > $config['high']) {
    sendTelegram($botToken, $chatId, "Цена BTC поднялась выше ({$config['high']}$). Текущая цена: {$price}$");
}
