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
        $content = mb_strtolower($sender . ' ' . $rawBody);
        return str_contains($content, 'uzcard') 
            || str_contains($content, 'yagona umumrespublika')
            || str_contains($content, 'quyidagi amaliyot');
    }

    public function parse(string $rawBody): TransactionDTO
    {
        // 1. Определение типа операции (Pokupka или Spisanie)
        $type = 'транзакция';
        if (stripos($rawBody, 'Pokupka:') !== false) {
            $type = 'покупка';
        } elseif (preg_match('/Spisanie\s+[cс]\s+karty:/ui', $rawBody)) {
            $type = 'списание';
        }

        // 2. Сумма и баланс
        preg_match('/summa\s*:\s*([0-9\s.,]+(?:UZS|сум)?)/ui', $rawBody, $amountMatches);
        preg_match('/balans\s*:\s*([0-9\s.,]+(?:UZS|сум)?)/ui', $rawBody, $balanceMatches);

        if (empty($amountMatches[1])) {
            throw new UnsupportedNotificationException('Не удалось извлечь сумму операции из письма Uzcard.');
        }

        // 3. Карта и дата/время
        preg_match('/karta\s*([*0-9]+)/ui', $rawBody, $cardMatches);
        preg_match('/(\d{2}\.\d{2}\.\d{2}\s+\d{2}:\d{2})/ui', $rawBody, $dateMatches);

        // 4. Извлечение блока деталей: всё между заголовком операции и датой/картой
        $merchant = null;
        $location = null;

        if (preg_match('/(?:Pokupka|Spisanie\s+[cс]\s+karty):\s*(.+?)(?=\s*,?\s*\d{2}\.\d{2}\.\d{2}|\s+karta)/ui', $rawBody, $detailsMatch)) {
            $rawDetails = trim($detailsMatch[1]);
            $parts = explode(',', $rawDetails);

            // Первое значение — всегда название мерчанта/организации
            $merchant = trim(array_shift($parts));

            // Всё, что осталось после первой запятой — адрес / локация
            if (!empty($parts)) {
                $location = trim(implode(',', $parts));
                $location = trim($location, ", \t\n\r");
            }
        }

        return new TransactionDTO(
            amount: trim($amountMatches[1]),
            balance: isset($balanceMatches[1]) ? trim($balanceMatches[1]) : 'Не указан',
            type: $type,
            merchant: $merchant,
            location: $location ?: null,
            cardNumber: isset($cardMatches[1]) ? trim($cardMatches[1]) : null,
            timestamp: $dateMatches[1] ?? null
        );
    }
}