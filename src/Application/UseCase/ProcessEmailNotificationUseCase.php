<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Enum\Locale;
use App\Domain\Exception\InvalidEmailPayloadException;
use App\Infrastructure\Contract\EmailParserInterface;
use App\Infrastructure\Contract\NotificationSenderInterface;
use App\Infrastructure\Parser\GoogleForwardingParser;
use App\Infrastructure\Storage\UserLocaleStorage;
use App\Infrastructure\Telegram\TelegramBotClient;

final readonly class ProcessEmailNotificationUseCase
{
    public function __construct(
        private EmailParserInterface $uzcardParser,
        private GoogleForwardingParser $googleParser,
        private NotificationSenderInterface $notificationSender,
        private TelegramBotClient $telegramClient,
        private UserLocaleStorage $localeStorage
    ) {}

    public function execute(string $to, string $from, string $rawBody): void
    {
        $chatId = $this->extractChatId($to, $rawBody);
        if (!$chatId) {
            throw new InvalidEmailPayloadException("Не удалось определить Telegram ID получателя из адреса: {$to}");
        }

        $locale = $this->localeStorage->get($chatId);

        // 1. Подтверждение пересылки от Google
        if ($this->googleParser->supports($from, $rawBody)) {
            $data = $this->googleParser->parse($rawBody);
            $this->sendGoogleConfirmationAlert($chatId, $data['confirmUrl'], $data['code'], $locale);
            return;
        }

        // 2. Письмо со ссылкой подтверждения от Uzcard (сервис 4800)
        if ($this->isUzcardVerificationEmail($from, $rawBody)) {
            $this->handleUzcardConfirmationAlert($chatId, $rawBody, $locale);
            return;
        }

        // 3. Обработка обычного чека транзакции
        if (!$this->uzcardParser->supports($from, $rawBody)) {
            throw new InvalidEmailPayloadException('Письмо не опознано как уведомление Uzcard.');
        }

        $transaction = $this->uzcardParser->parse($rawBody);
        $this->notificationSender->sendTransactionAlert((string)$chatId, $transaction, $locale);
    }

    private function extractChatId(string $to, string $rawBody): ?int
    {
        if (preg_match('/(?:card_|\+)(\d+)/i', $to, $matches)) {
            return (int)$matches[1];
        }

        if (preg_match('/(?:card_|\+)(\d+)@/i', $rawBody, $matches)) {
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

    private function handleUzcardConfirmationAlert(int $chatId, string $rawBody, Locale $locale): void
    {
        $cleanBody = quoted_printable_decode($rawBody);
        $cleanBody = str_replace(["\r\n", "\n", "\r"], ' ', $cleanBody);

        preg_match('/https?:\/\/[^\s"\'<>]+mail-confirmer\/confirm\/[a-zA-Z0-9]+/i', $cleanBody, $urlMatches);
        $confirmUrl = $urlMatches[0] ?? null;

        if (!$confirmUrl && preg_match('/https?:\/\/webapi\.uzcard\.uz:[0-9]+[^\s"\'<>]+/i', $cleanBody, $fallbackMatches)) {
            $confirmUrl = $fallbackMatches[0];
        }

        if ($confirmUrl) {
            [$text, $btnText] = match ($locale) {
                Locale::UZ => [
                    "🔐 *Uzcard pochta tasdiqlash*\n\n"
                    . "Uzcard xabarnomalarni faollashtirish uchun havola yubordi.\n"
                    . "⏳ *Havola 10 daqiqa amal qiladi!*\n\n"
                    . "Tasdiqlash uchun quyidagi tugmani bosing 👇",
                    '✅ Uzcard pochtani tasdiqlash'
                ],
                Locale::EN => [
                    "🔐 *Uzcard Email Confirmation*\n\n"
                    . "Uzcard sent a link to activate your notifications.\n"
                    . "⏳ *The link is valid for 10 minutes!*\n\n"
                    . "Click the button below to confirm 👇",
                    '✅ Confirm Uzcard Email'
                ],
                Locale::RU => [
                    "🔐 *Подтверждение почты в Uzcard*\n\n"
                    . "Uzcard прислал ссылку для активации уведомлений.\n"
                    . "⏳ *Ссылка действует 10 минут!*\n\n"
                    . "Нажми на кнопку ниже, чтобы открыть страницу и подтвердить привязку 👇",
                    '✅ Подтвердить почту в Uzcard'
                ],
            };

            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        ['text' => $btnText, 'url' => $confirmUrl]
                    ]
                ]
            ];

            $this->telegramClient->sendMessage($chatId, $text, $replyMarkup);
        } else {
            $fallbackMsg = match ($locale) {
                Locale::UZ => "📩 Uzcard tasdiqlash xati olindi, lekin havolani ajratib bo'lmadi. Pochtangizni tekshiring.",
                Locale::EN => "📩 Uzcard confirmation email received, but the link could not be extracted. Please check your inbox.",
                Locale::RU => "📩 Получено письмо подтверждения от Uzcard, но не удалось извлечь ссылку. Проверь входящие на почте.",
            };

            $this->telegramClient->sendMessage($chatId, $fallbackMsg);
        }
    }

    private function sendGoogleConfirmationAlert(int $chatId, ?string $confirmUrl, ?string $code, Locale $locale): void
    {
        $text = match ($locale) {
            Locale::UZ => "🔐 *Gmail yo'naltirishni tasdiqlash*\n\nGoogle xatlarni ushbu botga yo'naltirishni tasdiqlashni so'radi.\n\n",
            Locale::EN => "🔐 *Gmail Forwarding Confirmation*\n\nGoogle requested confirmation to forward emails to this bot.\n\n",
            Locale::RU => "🔐 *Подтверждение пересылки Gmail*\n\nGoogle запросил подтверждение пересылки писем в этот бот.\n\n",
        };

        if ($code) {
            $codeLabel = match ($locale) {
                Locale::UZ => 'Tasdiqlash kodi',
                Locale::EN => 'Confirmation code',
                Locale::RU => 'Код подтверждения',
            };
            $text .= "🔢 *{$codeLabel}:* `{$code}`\n\n";
        }

        $replyMarkup = null;
        if ($confirmUrl) {
            [$promptText, $btnText] = match ($locale) {
                Locale::UZ => ["Faollashtirish uchun quyidagi tugmani bosing 👇", '✅ Yo\'naltirishni tasdiqlash'],
                Locale::EN => ["Click the button below to activate 👇", '✅ Confirm forwarding'],
                Locale::RU => ["Нажми кнопку ниже для активации 👇", '✅ Подтвердить пересылку'],
            };

            $text .= $promptText;
            $replyMarkup = [
                'inline_keyboard' => [
                    [
                        ['text' => $btnText, 'url' => $confirmUrl]
                    ]
                ]
            ];
        }

        $this->telegramClient->sendMessage($chatId, $text, $replyMarkup);
    }
}
