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
        $message = "💳 *Новая транзакция*\n\n"
            . "💸 *Сумма:* `{$transaction->amount}`\n"
            . "💰 *Баланс:* `{$transaction->balance}`\n";

        if ($transaction->cardNumber) {
            $message .= "🔢 *Карта:* `{$transaction->cardNumber}`\n";
        }
        if ($transaction->merchant) {
            $message .= "📍 *Место:* {$transaction->merchant}\n";
        }

        $this->client->sendMessage((int)$recipientIdentifier, $message);
    }

    public function sendTextMessage(string $recipientIdentifier, string $message): void
    {
        $this->client->sendMessage((int)$recipientIdentifier, $message);
    }
}