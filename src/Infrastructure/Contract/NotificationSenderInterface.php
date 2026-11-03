<?php

declare(strict_types=1);

namespace App\Infrastructure\Contract;

use App\Application\DTO\TransactionDTO;

interface NotificationSenderInterface
{
    public function sendTransactionAlert(string $recipientIdentifier, TransactionDTO $transaction): void;
    public function sendTextMessage(string $recipientIdentifier, string $message): void;
}