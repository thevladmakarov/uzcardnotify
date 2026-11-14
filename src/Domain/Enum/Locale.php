<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum Locale: string
{
    case RU = 'ru';
    case UZ = 'uz';
    case EN = 'en';

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return match ($this) {
            self::UZ => [
                'header'   => 'Yangi tranzaksiya',
                'amount'   => 'Summa',
                'balance'  => 'Balans',
                'card'     => 'Karta',
                'time'     => 'Sana va vaqt',
                'location' => 'Manzil',
                'merchant' => 'Tashkilot',
            ],
            self::EN => [
                'header'   => 'New transaction',
                'amount'   => 'Amount',
                'balance'  => 'Balance',
                'card'     => 'Card',
                'time'     => 'Date & time',
                'location' => 'Location',
                'merchant' => 'Merchant',
            ],
            self::RU => [
                'header'   => 'Новая транзакция',
                'amount'   => 'Сумма',
                'balance'  => 'Баланс',
                'card'     => 'Карта',
                'time'     => 'Дата и время',
                'location' => 'Место',
                'merchant' => 'Организация',
            ],
        };
    }
}
