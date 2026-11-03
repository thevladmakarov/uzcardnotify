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
        // 1. Сумма: summa:22242.00 UZS
        preg_match('/summa\s*:\s*([0-9\s.,]+(?:UZS|сум)?)/ui', $rawBody, $amountMatches);

        // 2. Баланс: balans:98501.93 UZS
        preg_match('/balans\s*:\s*([0-9\s.,]+(?:UZS|сум)?)/ui', $rawBody, $balanceMatches);

        // 3. Карта: karta ***0057
        preg_match('/karta\s*([*0-9]+)/ui', $rawBody, $cardMatches);

        // 4. Тип операции и место (Pokupka или Spisanie c karty)
        $opType = 'Списание';
        if (preg_match('/(Pokupka|Spisanie\s+[cс]\s+karty):/ui', $rawBody, $opMatches)) {
            $opType = stripos($opMatches[1], 'Pokupka') !== false ? 'Покупка' : 'Списание (P2P/Перевод)';
        }

        // Вытаскиваем название торговой точки / сервиса
        $merchant = null;
        if (preg_match('/(?:Pokupka|Spisanie\s+[cс]\s+karty):\s*(.+?)(?:[,\s]+\d{2}\.\d{2}\.\d{2}|\s+karta)/ui', $rawBody, $merchantMatches)) {
            $merchant = trim($merchantMatches[1]);
        }

        // 5. Дата и время
        preg_match('/(\d{2}\.\d{2}\.\d{2}\s+\d{2}:\d{2})/ui', $rawBody, $dateMatches);
        $timestamp = $dateMatches[1] ?? null;

        if (empty($amountMatches[1])) {
            throw new UnsupportedNotificationException('Не удалось извлечь сумму операции из письма Uzcard.');
        }

        return new TransactionDTO(
            amount: trim($amountMatches[1]),
            balance: isset($balanceMatches[1]) ? trim($balanceMatches[1]) : 'Не указан',
            merchant: $merchant ? "{$opType}: {$merchant}" : $opType,
            cardNumber: isset($cardMatches[1]) ? trim($cardMatches[1]) : null,
            timestamp: $timestamp
        );
    }
}