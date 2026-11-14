<?php

declare(strict_types=1);

namespace App\Infrastructure\Contract;

use App\Application\DTO\TransactionDTO;
use App\Domain\Enum\Locale;

interface NotificationSenderInterface
{
    public function sendTransactionAlert(
        string $recipientIdentifier,
        TransactionDTO $transaction,
        Locale $locale = Locale::RU
    ): void;

    public function sendTextMessage(string $recipientIdentifier, string $message): void;
}
