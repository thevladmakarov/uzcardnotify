<?php

declare(strict_types=1);

namespace App\Infrastructure\Parser;

use App\Application\DTO\TransactionDTO;
use App\Domain\Exception\UnsupportedNotificationException;
use App\Infrastructure\Contract\EmailParserInterface;

final class UzcardEmailParser implements EmailParserInterface
{
    public function supports(string $sender, string $rawBody): bool
    {
        return str_contains(mb_strtolower($sender), 'uzcard') 
            || str_contains(mb_strtolower($rawBody), 'uzcard');
    }

    public function parse(string $rawBody): TransactionDTO
    {
        preg_match('/(?:Списание|Оплата|Spisanie):\s*([0-9\s.,]+(?:UZS|сум)?)/ui', $rawBody, $amountMatches);
        preg_match('/(?:Остаток|Баланс|Dostupno):\s*([0-9\s.,]+(?:UZS|сум)?)/ui', $rawBody, $balanceMatches);
        preg_match('/(?:Карта|Karta):\s*(\*?\d{4})/ui', $rawBody, $cardMatches);
        preg_match('/(?:Пункт обслуживания|Terminal|Mesto):\s*([^\n\r]+)/ui', $rawBody, $merchantMatches);

        if (empty($amountMatches[1])) {
            throw new UnsupportedNotificationException('Не удалось извлечь сумму операции из тела письма.');
        }

        return new TransactionDTO(
            amount: trim($amountMatches[1]),
            balance: isset($balanceMatches[1]) ? trim($balanceMatches[1]) : 'Не указан',
            merchant: isset($merchantMatches[1]) ? trim($merchantMatches[1]) : null,
            cardNumber: isset($cardMatches[1]) ? trim($cardMatches[1]) : null
        );
    }
}