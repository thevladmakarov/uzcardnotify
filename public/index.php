<?php

declare(strict_types=1);

use App\Application\UseCase\ProcessEmailNotificationUseCase;
use App\Infrastructure\Parser\UzcardEmailParser;
use App\Infrastructure\Telegram\TelegramBotClient;
use App\Infrastructure\Telegram\TelegramNotificationSender;

require_once __DIR__ . '/../vendor/autoload.php';

$botToken = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$secretKey = getenv('WEBHOOK_SECRET_KEY') ?: '';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// 1. Вебхук от Cloudflare
if ($uri === '/webhook/email' && $method === 'POST') {
    $authHeader = $_SERVER['HTTP_X_SECRET_TOKEN'] ?? '';
    if (!hash_equals($secretKey, $authHeader)) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $botClient = new TelegramBotClient($botToken);
    $sender = new TelegramNotificationSender($botClient);
    $parsers = [new UzcardEmailParser()];

    $useCase = new ProcessEmailNotificationUseCase($parsers, $sender);

    try {
        $useCase->execute(
            toAddress: $payload['to'] ?? '',
            fromAddress: $payload['from'] ?? '',
            rawEmailBody: $payload['raw'] ?? ''
        );
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } catch (\Throwable $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 2. Вебхук от Telegram (/start)
if ($uri === '/webhook/telegram' && $method === 'POST') {
    $update = json_decode(file_get_contents('php://input'), true) ?? [];
    $chatId = $update['message']['chat']['id'] ?? null;
    $text = $update['message']['text'] ?? '';

    if ($chatId && str_starts_with($text, '/start')) {
        $botClient = new TelegramBotClient($botToken);
        $domain = getenv('APP_EMAIL_DOMAIN') ?: 'yourdomain.com';
        $userEmail = "card_{$chatId}@{$domain}";

        $welcome = "👋 Привет!\n\nТвой личный email для получения уведомлений:\n`{$userEmail}`\n\n"
                 . "Настрой в своей почте пересылку писем от Uzcard на этот адрес.";
        
        $botClient->sendMessage((int)$chatId, $welcome);
    }

    http_response_code(200);
    echo 'OK';
    exit;
}

http_response_code(404);
echo 'Not Found';