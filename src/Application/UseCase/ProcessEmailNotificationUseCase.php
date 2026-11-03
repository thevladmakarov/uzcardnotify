<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Exception\InvalidEmailPayloadException;
use App\Infrastructure\Contract\EmailParserInterface;
use App\Infrastructure\Contract\NotificationSenderInterface;
use App\Infrastructure\Parser\GoogleForwardingParser;
use App\Infrastructure\Telegram\TelegramBotClient;

final readonly class ProcessEmailNotificationUseCase
{
    public function __construct(
        private EmailParserInterface $uzcardParser,
        private GoogleForwardingParser $googleParser,
        private NotificationSenderInterface $notificationSender,
        private TelegramBotClient $telegramClient
    ) {}

    public function execute(string $to, string $from, string $rawBody): void
    {
        // Извлекаем Telegram Chat ID (поддерживает card_1363700434@... и логин+1363700434@...)
        $chatId = $this->extractChatId($to, $rawBody);
        if (!$chatId) {
            throw new InvalidEmailPayloadException("Не удалось определить Telegram ID получателя из адреса: {$to}");
        }

        // 1. Проверяем, не подтверждение ли это от Google
        if ($this->googleParser->supports($from, $rawBody)) {
            $data = $this->googleParser->parse($rawBody);
            $this->sendGoogleConfirmationAlert($chatId, $data['confirmUrl'], $data['code']);
            return;
        }

        // 2. Обычная обработка чека Uzcard
        if (!$this->uzcardParser->supports($from, $rawBody)) {
            throw new InvalidEmailPayloadException('Письмо не опознано как уведомление Uzcard.');
        }

        $transaction = $this->uzcardParser->parse($rawBody);
        $this->notificationSender->sendTransactionAlert((string)$chatId, $transaction);
    }

    private function extractChatId(string $to, string $rawBody): ?int
    {
        // Ищем в поле to
        if (preg_match('/(?:card_|[\+])(\d+)/i', $to, $matches)) {
            return (int)$matches[1];
        }

        // Запасной поиск в теле письма (заголовки Delivered-To / X-Forwarded-To)
        if (preg_match('/(?:card_|[\+])(\d+)@/i', $rawBody, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    private function sendGoogleConfirmationAlert(int $chatId, ?string $confirmUrl, ?string $code): void
    {
        $text = "🔐 *Подтверждение пересылки Gmail*\n\n"
              . "Google запросил подтверждение пересылки писем в этот бот.\n\n";

        if ($code) {
            $text .= "🔢 *Код подтверждения:* `{$code}`\n\n";
        }

        $replyMarkup = null;
        if ($confirmUrl) {
            $text .= "Нажми кнопку ниже, чтобы активировать пересылку без ввода кода 👇";
            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Подтвердить пересылку', 'url' => $confirmUrl]
                    ]
                ]
            ];
        }

        $this->telegramClient->sendMessage($chatId, $text, $replyMarkup);
    }
}