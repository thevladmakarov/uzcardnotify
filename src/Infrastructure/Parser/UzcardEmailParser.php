<?php

declare(strict_types=1);

namespace App\Infrastructure\Parser;

use App\Domain\Entity\Transaction;
use App\Infrastructure\Contract\EmailParserInterface;

final class UzcardEmailParser implements EmailParserInterface
{
    public function supports(string $from, string $rawBody): bool
    {
        $content = mb_strtolower($from . ' ' . $rawBody);
        return str_contains($content, 'uzcard') 
            && (str_contains($content, 'summa:') || str_contains($content, 'balans:'));
    }

    public function parse(string $rawBody): Transaction
    {
        $decoded = quoted_printable_decode($rawBody);
        $clean = strip_tags($decoded);
        $clean = preg_replace('/[ \t]+/', ' ', $clean) ?? $clean;

        // 1. Извлечение суммы
        preg_match('/summa:\s*([\d.]+)\s*UZS/i', $clean, $sumMatches);
        $amount = isset($sumMatches[1]) ? (float)$sumMatches[1] : 0.0;

        // 2. Извлечение баланса
        preg_match('/balans:\s*([\d.]+)\s*UZS/i', $clean, $balMatches);
        $balance = isset($balMatches[1]) ? (float)$balMatches[1] : 0.0;

        // 3. Извлечение маски карты
        preg_match('/karta\s*\*{3}(\d{4})/i', $clean, $cardMatches);
        $cardMask = isset($cardMatches[1]) ? '***' . $cardMatches[1] : '****';

        // 4. Извлечение даты и времени
        preg_match('/(\d{2}\.\d{2}\.\d{2}\s+\d{2}:\d{2})/i', $clean, $dateMatches);
        $dateTime = $dateMatches[1] ?? date('d.m.y H:i');

        // 5. Определение типа операции и мерчанта/деталей
        $type = 'транзакция';
        $details = null;

        if (preg_match('/(Pokupka|E-Com oplata|Perevod na kartu|Popolnenie scheta):\s*([^,\n\r]+)/i', $clean, $opMatches)) {
            $rawType = strtolower($opMatches[1]);
            $details = trim($opMatches[2]);

            $type = match ($rawType) {
                'pokupka' => 'покупка',
                'e-com oplata' => 'онлайн-оплата',
                'perevod na kartu' => 'входящий перевод 📥',
                'popolnenie scheta' => 'пополнение счёта 📥',
                default => 'транзакция',
            };
        }

        return new Transaction(
            type: $type,
            amount: $amount,
            balance: $balance,
            cardMask: $cardMask,
            dateTime: $dateTime,
            currency: 'UZS',
            merchant: $details
        );
    }
}
