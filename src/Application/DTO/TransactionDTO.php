<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Enum\TransactionType;

final readonly class TransactionDTO
{
    public function __construct(
        public string $amount,
        public string $balance,
        public TransactionType $type,
        public ?string $merchant,
        public ?string $location,
        public string $cardNumber,
        public string $timestamp
    ) {
    }
}
