<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class TransactionDTO
{
    public function __construct(
        public string $amount,
        public string $balance,
        public string $type = 'транзакция',
        public ?string $merchant = null,
        public ?string $location = null,
        public ?string $cardNumber = null,
        public ?string $timestamp = null
    ) {}
}