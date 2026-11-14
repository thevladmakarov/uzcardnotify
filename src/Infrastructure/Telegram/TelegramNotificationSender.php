<?php

declare(strict_types=1);

namespace App\Infrastructure\Telegram;

use App\Application\DTO\TransactionDTO;
use App\Domain\Enum\Locale;
use App\Infrastructure\Contract\NotificationSenderInterface;

final readonly class TelegramNotificationSender implements NotificationSenderInterface
{
    public function __construct(
        private TelegramBotClient $client
    ) {}

    public function sendTransactionAlert(
        string $recipientIdentifier,
        TransactionDTO $transaction,
        Locale $locale = Locale::RU
    ): void {
        $labels = $locale->labels();
        $typeTitle = $transaction->type->title($locale);

        $lines = [
            "💳 *{$labels['header']}: {$typeTitle}*\n",
            "💸 *{$labels['amount']}:* `{$transaction->amount} UZS`",
            "💰 *{$labels['balance']}:* `{$transaction->balance} UZS`",
        ];

        if ($transaction->cardNumber) {
            $lines[] = "🔢 *{$labels['card']}:* `{$transaction->cardNumber}`";
        }

        if ($transaction->timestamp) {
            $lines[] = "🕒 *{$labels['time']}:* {$transaction->timestamp}";
        }

        if ($transaction->location) {
            $lines[] = "📍 *{$labels['location']}:* {$transaction->location}";
        }

        if ($transaction->merchant) {
            $lines[] = "🏢 *{$labels['merchant']}:* {$transaction->merchant}";
        }

        $message = implode("\n", $lines);

        $this->client->sendMessage((int)$recipientIdentifier, $message);
    }

    public function sendTextMessage(string $recipientIdentifier, string $message): void
    {
        $this->client->sendMessage((int)$recipientIdentifier, $message);
    }
}
