<?php

declare(strict_types=1);

use App\Application\UseCase\ProcessEmailNotificationUseCase;
use App\Domain\Enum\Locale;
use App\Infrastructure\Parser\GoogleForwardingParser;
use App\Infrastructure\Parser\UzcardEmailParser;
use App\Infrastructure\Storage\UserLocaleStorage;
use App\Infrastructure\Telegram\TelegramBotClient;
use App\Infrastructure\Telegram\TelegramNotificationSender;

require_once __DIR__ . '/../vendor/autoload.php';

$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$secretKey = getenv('WEBHOOK_SECRET_KEY') ?: '';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$localeStorage = new UserLocaleStorage();

/**
 * Формирует текст приветствия на выбранном языке с неизменной почтой
 */
function buildWelcomeMessage(int $chatId, Locale $locale): string
{
    $userEmail = "vovamakarov7733+{$chatId}@gmail.com";

    return match ($locale) {
        Locale::UZ => "👋 *Uzcard Notify botiga xush kelibsiz!*\n\n"
            . "Xabarnomalar uchun shaxsiy emailingiz:\n"
            . "`{$userEmail}`\n"
            . "*(nusxalash uchun manzil ustiga bosing)*\n\n"
            . "📋 *1 daqiqada ulash bo'yicha yo'riqnoma:*\n\n"
            . "1️⃣ `4800` raqamiga `Infoset` matni bilan SMS yuboring\n"
            . "2️⃣ Javob xabarida `2` raqamini yuboring (E-mail)\n"
            . "3️⃣ Yuqoridagi nusxalangan email manzilni yuboring\n\n"
            . "⚡ *Tayyor!* Bot Uzcard tasdiqlash havolasini o'zi ushlaydi va cheklarni yuborishni boshlaydi.",

        Locale::EN => "👋 *Welcome to Uzcard Notify!*\n\n"
            . "Your personal notification email:\n"
            . "`{$userEmail}`\n"
            . "*(tap the address to copy)*\n\n"
            . "📋 *How to connect notifications in 1 minute:*\n\n"
            . "1️⃣ Send an SMS with `Infoset` to `4800`\n"
            . "2️⃣ Reply with `2` (E-mail)\n"
            . "3️⃣ Paste and send the copied email address above\n\n"
            . "⚡ *Done!* The bot will automatically catch the Uzcard confirmation link and start sending receipts.",

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
}

$languageKeyboard = [
    'inline_keyboard' => [
        [
            ['text' => "🇺🇿 O'zbekcha", 'callback_data' => 'lang_uz'],
            ['text' => '🇷🇺 Русский', 'callback_data' => 'lang_ru'],
            ['text' => '🇬🇧 English', 'callback_data' => 'lang_en'],
        ]
    ]
];

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
        telegramClient: $telegramClient,
        localeStorage: $localeStorage
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

// 3. Вебхук от Telegram (/start, /lang и переключение языка)
if ($uri === '/webhook/telegram' && $method === 'POST') {
    $update = json_decode(file_get_contents('php://input'), true) ?? [];
    $botClient = new TelegramBotClient($botToken);

    // 3.1. Команды /start или /lang
    $chatId = $update['message']['chat']['id'] ?? null;
    $text = trim($update['message']['text'] ?? '');

    if ($chatId && (str_starts_with($text, '/start') || str_starts_with($text, '/lang'))) {
        $currentLocale = $localeStorage->get((int)$chatId);
        $welcome = buildWelcomeMessage((int)$chatId, $currentLocale);

        $botClient->sendMessage((int)$chatId, $welcome, $languageKeyboard);
    }

    // 3.2. Нажатие на инлайн-кнопку смены языка
    if (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $callbackId = (string)($callback['id'] ?? '');
        $cbChatId = $callback['message']['chat']['id'] ?? null;
        $messageId = $callback['message']['message_id'] ?? null;
        $data = (string)($callback['data'] ?? '');

        if ($cbChatId && $messageId && str_starts_with($data, 'lang_')) {
            $langCode = substr($data, 5);
            $newLocale = Locale::tryFrom($langCode) ?? Locale::RU;

            $localeStorage->save((int)$cbChatId, $newLocale);

            $updatedWelcome = buildWelcomeMessage((int)$cbChatId, $newLocale);
            $botClient->editMessageText((int)$cbChatId, (int)$messageId, $updatedWelcome, $languageKeyboard);

            $toast = match ($newLocale) {
                Locale::UZ => "🇺🇿 O'zbek tili tanlandi",
                Locale::EN => '🇬🇧 English selected',
                Locale::RU => '🇷🇺 Выбран русский язык',
            };
            $botClient->answerCallbackQuery($callbackId, $toast);
        }
    }

    http_response_code(200);
    echo 'OK';
    exit;
}

// 4. Fallback 404
http_response_code(404);
echo 'Not Found';
