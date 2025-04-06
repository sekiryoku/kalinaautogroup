<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Функция для получения IP клиента
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return $_SERVER['REMOTE_ADDR'];
}

// Путь к файлу для хранения запросов
$rateLimitFile = __DIR__ . '/rate_limit.json';
$clientIP = getClientIP();
$currentTime = time();
$limitWindow = 60; // 60 секунд
$maxRequests = 3;

// Загружаем данные о запросах
if (file_exists($rateLimitFile)) {
    $data = json_decode(file_get_contents($rateLimitFile), true) ?: [];
} else {
    $data = [];
}

// Если для данного IP нет данных, инициализируем массив
if (!isset($data[$clientIP])) {
    $data[$clientIP] = [];
}

// Удаляем старые запросы
$data[$clientIP] = array_filter($data[$clientIP], function($timestamp) use ($currentTime, $limitWindow) {
    return ($currentTime - $timestamp) < $limitWindow;
});

// Проверяем лимит запросов
if (count($data[$clientIP]) >= $maxRequests) {
    http_response_code(429);
    echo json_encode(["ok" => false, "message" => "Слишком много запросов."]);
    exit;
}

// Добавляем текущий запрос в лог
$data[$clientIP][] = $currentTime;
file_put_contents($rateLimitFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | LOCK_EX));

// Установка часового пояса для Украины и форматирование даты
$date = new DateTime('now', new DateTimeZone('Europe/Kiev'));
$formattedDate = $date->format('d.m.Y H:i:s');

// Telegram API
$TELEGRAM_BOT_TOKEN = "7858549417:AAGOx3iZuSpJ2kXISW4yfho5c3i2hrptXAk";
$TELEGRAM_CHAT_ID = "-1002582688057";

// Проверяем метод запроса
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "message" => "Метод запроса не поддерживается"]);
    exit;
}

// Определяем, какая это форма (обычная или автозапчастей)
if (isset($_POST["form-type"]) && $_POST["form-type"] === "auto-parts") {
    // Обработка заявки на автозапчасть
    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $brand = trim($_POST["car-brand"] ?? "");
    $model = trim($_POST["car-model"] ?? "");
    $partDescription = trim($_POST["part-description"] ?? "");

    // Проверяем обязательные поля (можно добавить и другие проверки)
    if (empty($name) || empty($phone) || empty($brand) || empty($model) || empty($partDescription)) {
        http_response_code(400);
        echo json_encode(["ok" => false, "message" => "Все поля обязательны для заявки на автозапчасть"]);
        exit;
    }

    $message = "📩 Заявка на автозапчасть!\n"
        . "Имя: $name\n"
        . "Телефон: $phone\n"
        . "Марка машины: $brand\n"
        . "Модель: $model\n"
        . "Описание детали: $partDescription\n";
} else {
    // Обработка обычной формы (модалка, футер и т.д.)
    $name = trim($_POST["first-name"] ?? $_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $budget = trim($_POST["budget"] ?? "");
    $model = trim($_POST["car-model"] ?? $_POST["model"] ?? "");
    $brand = trim($_POST["car-brand"] ?? "");
    $comment = trim($_POST["part-description"] ?? $_POST["comment"] ?? "");

    // Проверяем обязательные поля
    if (empty($name) || empty($phone)) {
        http_response_code(400);
        echo json_encode(["ok" => false, "message" => "Имя и телефон обязательны"]);
        exit;
    }

    $message = "📩 Новая заявка!\n"
        . "Имя: $name\n"
        . "Телефон: $phone\n"
        . (!empty($brand) ? "Марка авто: $brand\n" : "")
        . (!empty($model) ? "Модель авто: $model\n" : "")
        . (!empty($budget) ? "Бюджет: $budget\n" : "")
        . (!empty($comment) ? "Комментарий: $comment\n" : "");
}

// Добавляем дату заявки
$message .= "Дата заявки: $formattedDate";

// Отправка в Telegram
$telegramUrl = "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage";
$dataSend = [
    "chat_id" => $TELEGRAM_CHAT_ID,
    "text" => $message
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $telegramUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dataSend));

$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo json_encode(["ok" => true, "message" => "Сообщение отправлено"]);
} else {
    http_response_code(500);
    echo json_encode(["ok" => false, "message" => "Ошибка при отправке"]);
}
?>
