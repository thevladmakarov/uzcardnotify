<?php

declare(strict_types=1);

namespace App\Infrastructure\Parser;

use App\Application\DTO\TransactionDTO;
use App\Infrastructure\Contract\EmailParserInterface;

final class UzcardEmailParser implements EmailParserInterface
{
    public function supports(string $from, string $rawBody): bool
    {
        $content = mb_strtolower($from . ' ' . $rawBody);

        return str_contains($content, 'uzcard') 
            && (
                str_contains($content, 'summa:') 
                || str_contains($content, 'balans:') 
                || str_contains($content, 'nedostatochno sredstv')
            );
    }

    public function parse(string $rawBody): TransactionDTO
    {
        $decoded = quoted_printable_decode($rawBody);
        $clean = strip_tags($decoded);
        $clean = preg_replace('/[ \t]+/', ' ', $clean) ?? $clean;

        // 0. Обработка отказа операции из-за недостатка средств
        if (str_contains(mb_strtolower($clean), 'nedostatochno sredstv')) {
            preg_match('/NEDOSTATOCHNO\s+SREDSTV\s+([0-9*]+)\s+(\d{2}\.\d{2}\.\d{2}\s+\d{2}:\d{2})\s+(.*?)\s*([\d.]+)\s*UZS/i', $clean, $failMatches);

            return new TransactionDTO(
                amount: $failMatches[4] ?? '0.00',
                balance: '—',
                type: '❌ Отказ: Недостаточно средств',
                merchant: trim($failMatches[3] ?? 'POS purchase'),
                location: null,
                cardNumber: $failMatches[1] ?? '****',
                timestamp: $failMatches[2] ?? date('d.m.y H:i')
            );
        }

        // 1. Извлечение суммы
        preg_match('/summa:\s*([\d.]+)\s*UZS/i', $clean, $sumMatches);
        $amount = $sumMatches[1] ?? '0.00';

        // 2. Извлечение баланса
        preg_match('/balans:\s*([\d.]+)\s*UZS/i', $clean, $balMatches);
        $balance = $balMatches[1] ?? '0.00';

        // 3. Извлечение маски карты
        preg_match('/karta\s*\*{3}(\d{4})/i', $clean, $cardMatches);
        $cardNumber = isset($cardMatches[1]) ? '***' . $cardMatches[1] : '****';

        // 4. Извлечение даты и времени
        preg_match('/(\d{2}\.\d{2}\.\d{2}\s+\d{2}:\d{2})/i', $clean, $dateMatches);
        $timestamp = $dateMatches[1] ?? date('d.m.y H:i');

        // 5. Определение типа операции и мерчанта
        $type = 'транзакция';
        $merchant = null;

        if (preg_match('/(OTMENA Pokupka|Vidacha nalichnykh v bankomate|Pokupka|E-Com oplata|Perevod na kartu|Popolnenie scheta|Spisanie c karty):\s*([^,\n\r]+)/i', $clean, $opMatches)) {
            $rawType = strtolower(trim($opMatches[1]));
            $merchant = trim($opMatches[2]);

            $type = match ($rawType) {
                'otmena pokupka' => 'отмена покупки 🔄',
                'vidacha nalichnykh v bankomate' => 'снятие наличных в банкомате 🏧',
                'pokupka' => 'покупка 🛒',
                'e-com oplata' => 'онлайн-оплата 🌐',
                'perevod na kartu' => 'входящий перевод 📥',
                'popolnenie scheta' => 'пополнение счёта 📥',
                'spisanie c karty' => 'исходящий перевод / списание 📤',
                default => 'транзакция',
            };
        }

        return new TransactionDTO(
            amount: $amount,
            balance: $balance,
            type: $type,
            merchant: $merchant,
            location: null,
            cardNumber: $cardNumber,
            timestamp: $timestamp
        );
    }
}
