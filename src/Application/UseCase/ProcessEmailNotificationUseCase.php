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

        // 1. Проверка на подтверждение пересылки от Google (если кто-то настраивает через Gmail)
        if ($this->googleParser->supports($from, $rawBody)) {
            $data = $this->googleParser->parse($rawBody);
            $this->sendGoogleConfirmationAlert($chatId, $data['confirmUrl'], $data['code']);
            return;
        }

        // 2. Проверка на письмо активации/подтверждения от Uzcard
        if ($this->isUzcardVerificationEmail($from, $rawBody)) {
            $this->handleUzcardAutoConfirm($chatId, $rawBody);
            return;
        }

        // 3. Обычная обработка чека транзакции
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
            && (str_contains($content, 'подтвержд') || str_contains($content, 'tasdiqlash') || str_contains($content, 'confirm'));
    }

    private function handleUzcardAutoConfirm(int $chatId, string $rawBody): void
    {
        // Ищем ссылку подтверждения в письме
        preg_match('/(https?:\/\/[^\s<>"\'\)]+(?:confirm|verify|token|activate)[^\s<>"\'\)]*)/i', $rawBody, $urlMatches);
        $confirmUrl = $urlMatches[1] ?? null;

        // Если специфичное слово не найдено, берем любую ссылку на uzcard.uz
        if (!$confirmUrl && preg_match('/(https?:\/\/[a-zA-Z0-9.\-]*uzcard\.uz[^\s<>"\'\)]+)/i', $rawBody, $fallbackMatches)) {
            $confirmUrl = $fallbackMatches[1];
        }

        if ($confirmUrl) {
            // Переходим по ссылке в фоне (cURL GET)
            $ch = curl_init($confirmUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ]);
            curl_exec($ch);
            curl_close($ch);

            $msg = "🎉 *Email успешно подтверждён в Uzcard!*\n\n"
                 . "Информирование активировано. Теперь все уведомления о покупках и списаниях будут мгновенно приходить сюда.";
            
            $this->telegramClient->sendMessage($chatId, $msg);
        } else {
            // Если ссылку вытащить не удалось, отправляем текст письма
            $this->telegramClient->sendMessage($chatId, "📩 Получено письмо подтверждения от Uzcard, но ссылку не удалось разобрать автоматически.");
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