<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum Locale: string
{
    case RU = 'ru';
    case UZ = 'uz';
    case EN = 'en';

    /**
     * Перевод названий полей чека
     * @return array<string, string>
     */
    public function labels(): array
    {
        return match ($this) {
            self::UZ => [
                'amount'   => 'Summa',
                'balance'  => 'Balans',
                'merchant' => 'Joy',
                'card'     => 'Karta',
                'time'     => 'Vaqt',
            ],
            self::EN => [
                'amount'   => 'Amount',
                'balance'  => 'Balance',
                'merchant' => 'Merchant',
                'card'     => 'Card',
                'time'     => 'Time',
            ],
            self::RU => [
                'amount'   => 'Сумма',
                'balance'  => 'Баланс',
                'merchant' => 'Точка',
                'card'     => 'Карта',
                'time'     => 'Время',
            ],
        };
    }
}
