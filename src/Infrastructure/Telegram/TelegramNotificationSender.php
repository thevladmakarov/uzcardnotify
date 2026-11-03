<?php

declare(strict_types=1);

namespace App\Infrastructure\Telegram;

use App\Application\DTO\TransactionDTO;
use App\Infrastructure\Contract\NotificationSenderInterface;

final readonly class TelegramNotificationSender implements NotificationSenderInterface
{
    public function __construct(
        private TelegramBotClient $client
    ) {}

    public function sendTransactionAlert(string $recipientIdentifier, TransactionDTO $transaction): void
    {
        $lines = [
            "💳 *Новая транзакция: {$transaction->type}*\n",
            "💸 *Сумма:* `{$transaction->amount}`",
            "💰 *Баланс:* `{$transaction->balance}`",
        ];

        if ($transaction->cardNumber) {
            $lines[] = "🔢 *Карта:* `{$transaction->cardNumber}`";
        }

        if ($transaction->timestamp) {
            $lines[] = "🕒 *Дата и время:* {$transaction->timestamp}";
        }

        if ($transaction->location) {
            $lines[] = "📍 *Место:* {$transaction->location}";
        }

        if ($transaction->merchant) {
            $lines[] = "🏢 *Организация:* {$transaction->merchant}";
        }

        $message = implode("\n", $lines);

        $this->client->sendMessage((int)$recipientIdentifier, $message);
    }

    public function sendTextMessage(string $recipientIdentifier, string $message): void
    {
        $this->client->sendMessage((int)$recipientIdentifier, $message);
    }
}