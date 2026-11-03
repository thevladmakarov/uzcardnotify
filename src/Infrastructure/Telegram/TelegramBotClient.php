<?php

declare(strict_types=1);

namespace App\Infrastructure\Telegram;

use RuntimeException;

final readonly class TelegramBotClient
{
    public function __construct(
        private string $token
    ) {}

    public function sendMessage(int $chatId, string $text): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        $ch = curl_init("https://api.telegram.org/bot{$this->token}/sendMessage");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_TIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException("Telegram API error. HTTP {$httpCode}: {$response}");
        }
    }
}