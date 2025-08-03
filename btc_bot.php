<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$configFile = __DIR__ . "/config.json";
$offsetFile = __DIR__ . "/last_update_id.txt";
$tokenFile = __DIR__ . "/token_chatid.php"; // где хранятся $botToken и $chatId

// Подключаем токен и чат ID
if (!file_exists($tokenFile)) {
    die("Файл с токеном и chatId не найден.");
}
include $tokenFile;

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
    file_get_contents($url, false, $context);
}

// Загружаем конфиг
if (!file_exists($configFile)) {
    file_put_contents($configFile, json_encode(['low' => 0, 'high' => 0, 'chatId' => 0], JSON_PRETTY_PRINT));
}
$config = json_decode(file_get_contents($configFile), true);

// Читаем последний offset
$lastUpdateId = 0;
if (file_exists($offsetFile)) {
    $lastUpdateId = (int)file_get_contents($offsetFile);
}

// Получаем новые обновления
$url = "https://api.telegram.org/bot{$botToken}/getUpdates?offset=" . ($lastUpdateId + 1);
$response = file_get_contents($url);
$data = json_decode($response, true);

if (!empty($data['result'])) {
    foreach ($data['result'] as $update) {
        $lastUpdateId = $update['update_id'];

        if (isset($update['message']['text'])) {
            $message = $update['message']['text'];
            $chatIdMsg = $update['message']['chat']['id'];

            // Проверяем, что сообщение от нужного чата (можно убрать, если надо принимать от всех)
            if ($chatIdMsg == $config['chatId'] || $config['chatId'] == 0) {

                // Команда /low
                if (preg_match('/^\/low\s+(\d+(\.\d+)?)/i', $message, $matches)) {
                    $config['low'] = floatval($matches[1]);
                    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
                    sendTelegram($botToken, $chatIdMsg, "✅ Нижний порог установлен: {$config['low']}$");
                    // Сохраняем chatId если ещё не установлен
                    if ($config['chatId'] == 0) {
                        $config['chatId'] = $chatIdMsg;
                        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
                    }
                }
                // Команда /high
                elseif (preg_match('/^\/high\s+(\d+(\.\d+)?)/i', $message, $matches)) {
                    $config['high'] = floatval($matches[1]);
                    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
                    sendTelegram($botToken, $chatIdMsg, "✅ Верхний порог установлен: {$config['high']}$");
                    if ($config['chatId'] == 0) {
                        $config['chatId'] = $chatIdMsg;
                        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
                    }
                }
            }
        }
    }
    // Сохраняем последний обработанный update_id
    file_put_contents($offsetFile, $lastUpdateId);
}
