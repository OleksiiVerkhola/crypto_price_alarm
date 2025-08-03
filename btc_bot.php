<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$tokenFile = __DIR__ . "/config_bot.php";
if (!file_exists($tokenFile)) {
    die("Файл config_bot.php не найден.\n");
}
include $tokenFile;

// Проверка аргументов
if ($argc < 3) {
    echo "Использование: php bot.php LOW HIGH\n";
    echo "Пример: php bot.php 25000 30000\n";
    exit(1);
}

$low  = floatval($argv[1]);
$high = floatval($argv[2]);

$apiUrl = "https://api.kucoin.com/api/v1/market/orderbook/level1?symbol=BTC-USDT";

// Функция отправки сообщений
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
    file_get_contents($url, false, $context);
}

echo "Бот запущен. Нижний порог: $low$, верхний порог: $high$\n";

while (true) {
    $response = @file_get_contents($apiUrl);
    if ($response) {
        $data = json_decode($response, true);
        $price = isset($data['data']['price']) ? floatval($data['data']['price']) : null;

        if ($price) {
            echo date("Y-m-d H:i:s") . " — Цена BTC: $price $\n";

            if ($price <= $low) {
                sendTelegram($botToken, $chatId, "Цена BTC упала ниже {$low}$: сейчас {$price}$");
            }
            if ($price >= $high) {
                sendTelegram($botToken, $chatId, "Цена BTC превысила {$high}$: сейчас {$price}$");
            }
        } else {
            echo "Ошибка: цена не получена.\n";
        }
    } else {
        echo "Ошибка запроса к API.\n";
    }

    sleep(60); // ждём 1 минуту
}
