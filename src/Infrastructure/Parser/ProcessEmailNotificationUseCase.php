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
        $chatId = $this->extractChatId($to, $rawBody);
        if (!$chatId) {
            throw new InvalidEmailPayloadException("Не удалось определить Telegram ID получателя из адреса: {$to}");
        }

        // 1. Подтверждение пересылки от Google (если настраивают через Gmail)
        if ($this->googleParser->supports($from, $rawBody)) {
            $data = $this->googleParser->parse($rawBody);
            $this->sendGoogleConfirmationAlert($chatId, $data['confirmUrl'], $data['code']);
            return;
        }

        // 2. Письмо со ссылкой подтверждения от Uzcard (сервис 4800)
        if ($this->isUzcardVerificationEmail($from, $rawBody)) {
            $this->handleUzcardConfirmationAlert($chatId, $rawBody);
            return;
        }

        // 3. Обработка чека транзакции
        if (!$this->uzcardParser->supports($from, $rawBody)) {
            throw new InvalidEmailPayloadException('Письмо не опознано как уведомление Uzcard.');
        }

        $transaction = $this->uzcardParser->parse($rawBody);
        $this->notificationSender->sendTransactionAlert((string)$chatId, $transaction);
    }

    private function extractChatId(string $to, string $rawBody): ?int
    {
        if (preg_match('/(?:card_|[\+])(\d+)/i', $to, $matches)) {
            return (int)$matches[1];
        }

        if (preg_match('/(?:card_|[\+])(\d+)@/i', $rawBody, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    private function isUzcardVerificationEmail(string $from, string $rawBody): bool
    {
        $content = mb_strtolower($from . ' ' . $rawBody);
        return (str_contains($content, 'uzcard') || str_contains($content, 'yagona'))
            && (str_contains($content, 'подтвержд') || str_contains($content, 'tasdiqlash') || str_contains($content, 'mail-confirmer'));
    }

    private function handleUzcardConfirmationAlert(int $chatId, string $rawBody): void
    {
        // Убираем мягкие переносы строк quoted-printable, чтобы ссылка не ломалась
        $cleanBody = str_replace(["=\r\n", "=\n", "\r\n", "\n"], '', $rawBody);

        // Ищем ссылку на mail-confirmer Uzcard
        preg_match('/(https?:\/\/[^\s<>"\'\)]*mail-confirmer\/confirm\/[a-zA-Z0-9]+)/i', $cleanBody, $urlMatches);
        $confirmUrl = $urlMatches[1] ?? null;

        // Запасной вариант поиска любой ссылки на подтверждение узкарда
        if (!$confirmUrl && preg_match('/href=["\'](https?:\/\/[^"\']*(?:confirm|verify)[^"\']*)["\']/i', $rawBody, $fallbackMatches)) {
            $confirmUrl = html_entity_decode($fallbackMatches[1]);
        }

        if ($confirmUrl) {
            $text = "🔐 *Подтверждение почты в Uzcard*\n\n"
                  . "Uzcard отправил ссылку для активации email-информирования.\n"
                  . "⏳ *Ссылка действует 10 минут!*\n\n"
                  . "Нажми на кнопку ниже, чтобы открыть страницу и подтвердить привязку 👇";

            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ Подтвердить почту в Uzcard', 'url' => $confirmUrl]
                    ]
                ]
            ];

            $this->telegramClient->sendMessage($chatId, $text, $replyMarkup);
        } else {
            $this->telegramClient->sendMessage(
                $chatId, 
                "📩 Получено письмо подтверждения от Uzcard, но не удалось автоматически извлечь кнопку. Проверьте почту."
            );
        }
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
            $text .= "Нажми кнопку ниже для активации 👇";
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