<?php

declare(strict_types=1);

namespace App\Infrastructure\Telegram;

final readonly class TelegramBotClient
{
    private const BASE_URL = 'https://api.telegram.org/bot';

    public function __construct(
        private string $token
    ) {}

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $url = self::BASE_URL . $this->token . '/sendMessage';

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->post($url, $payload);
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
        $url = self::BASE_URL . $this->token . '/editMessageText';

        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->post($url, $payload);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        if ($callbackQueryId === '') {
            return;
        }

        $url = self::BASE_URL . $this->token . '/answerCallbackQuery';
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        $this->post($url, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $url, array $payload): void
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}
