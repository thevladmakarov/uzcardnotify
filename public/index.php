<?php

declare(strict_types=1);

use App\Application\UseCase\ProcessEmailNotificationUseCase;
use App\Domain\Enum\Locale;
use App\Infrastructure\Parser\GoogleForwardingParser;
use App\Infrastructure\Parser\UzcardEmailParser;
use App\Infrastructure\Telegram\TelegramBotClient;
use App\Infrastructure\Telegram\TelegramNotificationSender;

require_once __DIR__ . '/../vendor/autoload.php';

$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$secretKey = getenv('WEBHOOK_SECRET_KEY') ?: '';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. Healthcheck для UptimeRobot и поддержания сервиса
if ($uri === '/' || $uri === '/health') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'service' => 'uzcardnotify']);
    exit;
}

// 2. Вебхук от почтового обработчика (Google Apps Script / Cloudflare)
if ($uri === '/webhook/email' && $method === 'POST') {
    $authHeader = $_SERVER['HTTP_X_SECRET_TOKEN'] ?? '';
    if (!hash_equals($secretKey, $authHeader)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];

    $telegramClient = new TelegramBotClient($botToken);
    $notificationSender = new TelegramNotificationSender($telegramClient);
    $googleParser = new GoogleForwardingParser();
    $uzcardParser = new UzcardEmailParser();

    $useCase = new ProcessEmailNotificationUseCase(
        uzcardParser: $uzcardParser,
        googleParser: $googleParser,
        notificationSender: $notificationSender,
        telegramClient: $telegramClient
    );

    try {
        $useCase->execute(
            to: $payload['to'] ?? '',
            from: $payload['from'] ?? '',
            rawBody: $payload['raw'] ?? ''
        );
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } catch (\Throwable $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 3. Вебхук от Telegram (/start, /lang и нажатия инлайн-кнопок)
if ($uri === '/webhook/telegram' && $method === 'POST') {
    $update = json_decode(file_get_contents('php://input'), true) ?? [];
    $botClient = new TelegramBotClient($botToken);

    // 3.1. Обработка команд /start и /lang
    $chatId = $update['message']['chat']['id'] ?? null;
    $text = trim($update['message']['text'] ?? '');

    if ($chatId && (str_starts_with($text, '/start') || str_starts_with($text, '/lang'))) {
        $prompt = "🌐 *Tilni tanlang / Выберите язык / Choose language:*";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => "🇺🇿 O'zbekcha", 'callback_data' => 'lang_uz'],
                    ['text' => '🇷🇺 Русский', 'callback_data' => 'lang_ru'],
                    ['text' => '🇬🇧 English', 'callback_data' => 'lang_en'],
                ]
            ]
        ];

        $botClient->sendMessage((int)$chatId, $prompt, $keyboard);
    }

    // 3.2. Обработка нажатия на кнопку выбора языка
    if (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $callbackId = (string)($callback['id'] ?? '');
        $cbChatId = $callback['message']['chat']['id'] ?? null;
        $data = (string)($callback['data'] ?? '');

        if ($cbChatId && str_starts_with($data, 'lang_')) {
            $langCode = substr($data, 5);
            $locale = Locale::tryFrom($langCode) ?? Locale::RU;

            $userEmail = "vovamakarov7733+{$locale->value}_{$cbChatId}@gmail.com";

            $welcome = match ($locale) {
                Locale::UZ => "👋 *Uzcard Notify botiga xush kelibsiz!*\n\n"
                    . "Xabarnomalar uchun shaxsiy emailingiz:\n"
                    . "`{$userEmail}`\n"
                    . "*(nusxalash uchun manzil ustiga bosing)*\n\n"
                    . "📋 *1 daqiqada ulash bo'yicha yo'riqnoma:*\n\n"
                    . "1️⃣ `4800` raqamiga `Infoset` matni bilan SMS yuboring\n"
                    . "2️⃣ Javob xabarida `2` raqamini yuboring (E-mail)\n"
                    . "3️⃣ Yuqoridagi nusxalangan email manzilni yuboring\n\n"
                    . "⚡ *Tayyor!* Bot Uzcard tasdiqlash havolasini o'zi ushlaydi va cheklarni shu tilda yuborishni boshlaydi.",

                Locale::EN => "👋 *Welcome to Uzcard Notify!*\n\n"
                    . "Your personal notification email:\n"
                    . "`{$userEmail}`\n"
                    . "*(tap the address to copy)*\n\n"
                    . "📋 *How to connect notifications in 1 minute:*\n\n"
                    . "1️⃣ Send an SMS with `Infoset` to `4800`\n"
                    . "2️⃣ Reply with `2` (E-mail)\n"
                    . "3️⃣ Paste and send the copied email address above\n\n"
                    . "⚡ *Done!* The bot will automatically catch the Uzcard confirmation link and start sending receipts in English.",

                Locale::RU => "👋 *Добро пожаловать в Uzcard Notify!*\n\n"
                    . "Твой персональный email для уведомлений:\n"
                    . "`{$userEmail}`\n"
                    . "*(нажми на адрес, чтобы скопировать)*\n\n"
                    . "📋 *Как подключить уведомления за 1 минуту:*\n\n"
                    . "1️⃣ Отправь SMS с текстом `Infoset` на номер `4800`\n"
                    . "2️⃣ В ответном сообщении отправь цифру `2` (E-mail)\n"
                    . "3️⃣ Вставь и отправь скопированный адрес выше\n\n"
                    . "⚡ *Всё!* Бот сам поймает ссылку подтверждения от Uzcard, активирует информирование и начнёт присылать чеки.",
            };

            $botClient->answerCallbackQuery($callbackId);
            $botClient->sendMessage((int)$cbChatId, $welcome);
        }
    }

    http_response_code(200);
    echo 'OK';
    exit;
}

// 4. Fallback 404
http_response_code(404);
echo 'Not Found';
