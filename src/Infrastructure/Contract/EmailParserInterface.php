<?php

declare(strict_types=1);

namespace App\Infrastructure\Contract;

use App\Application\DTO\TransactionDTO;

interface EmailParserInterface
{
    public function supports(string $sender, string $rawBody): bool;
    public function parse(string $rawBody): TransactionDTO;
}