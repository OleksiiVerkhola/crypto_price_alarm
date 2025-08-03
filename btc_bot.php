<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==== ЗАГРУЗКА КЛЮЧЕЙ ====
$keys = require __DIR__ . '/config_bot.php';
$botToken = $keys['botToken'];
$chatId   = $keys['chatId'];

// ==== НАСТРОЙКИ ====
$configFile = __DIR__ . "/config.json";
$apiUrl = "https://api.kucoin.com/api/v1/market/orderbook/level1?symbol=BTC-USDT";
$checkInterval = 60; // проверка каждые 60 секунд

// ==== СОЗДАЁМ КОНФИГ, ЕСЛИ ЕГО НЕТ ====
if (!file_exists($configFile)) {
    file_put_contents($configFile, json_encode(["low" => 0, "high" => 0], JSON_PRETTY_PRINT));
}

// ==== ФУНКЦИЯ ОТПРАВКИ В TELEGRAM ====
function sendTelegram($message) {
    global $botToken, $chatId;
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $message];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}

// ==== ФУНКЦИЯ ПОЛУЧЕНИЯ ПОСЛЕДНЕЙ КОМАНДЫ ====
function getLastCommand() {
    global $botToken;
    $url = "https://api.telegram.org/bot{$botToken}/getUpdates";

    $res = file_get_contents($url);
    if (!$res) return null;

    $data = json_decode($res, true);
    if (!isset($data['result'])) return null;

    $updates = $data['result'];
    if (empty($updates)) return null;

    $lastUpdate = end($updates);
    return [
        'text' => trim($lastUpdate['message']['text'] ?? ''),
        'id'   => $lastUpdate['message']['chat']['id'] ?? ''
    ];
}

// ==== ОСНОВНОЙ ЦИКЛ ====
sendTelegram("🤖 BTC Bot запущен!");
$lastPrice = null;

while (true) {
    // Загружаем текущие пороги
    $config = json_decode(file_get_contents($configFile), true);

    // Проверка команд
    $cmd = getLastCommand();
    if ($cmd && $cmd['id'] == $chatId) {
        if (preg_match("/^\/low\s+(\d+(\.\d+)?)/", $cmd['text'], $m)) {
            $config['low'] = floatval($m[1]);
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            sendTelegram("✅ Нижний порог установлен: {$config['low']}$");
        } elseif (preg_match("/^\/high\s+(\d+(\.\d+)?)/", $cmd['text'], $m)) {
            $config['high'] = floatval($m[1]);
            file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
            sendTelegram("✅ Верхний порог установлен: {$config['high']}$");
        }
    }

    // Получение текущей цены
    $response = @file_get_contents($apiUrl);
    if ($response) {
        $data = json_decode($response, true);
        $price = isset($data['data']['price']) ? floatval($data['data']['price']) : null;

        if ($price) {
            echo date("H:i:s") . " BTC: $price USD\n";

            // Уведомления
            if ($config['low'] > 0 && $price < $config['low']) {
                if ($lastPrice === null || $lastPrice >= $config['low']) {
                    sendTelegram("⚠ BTC упал ниже {$config['low']}$: сейчас $price$");
                }
            }
            if ($config['high'] > 0 && $price > $config['high']) {
                if ($lastPrice === null || $lastPrice <= $config['high']) {
                    sendTelegram("🚀 BTC вырос выше {$config['high']}$: сейчас $price$");
                }
            }
            $lastPrice = $price;
        }
    } else {
        echo "Ошибка получения цены\n";
    }

    sleep($checkInterval);
}
