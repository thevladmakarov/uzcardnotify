<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum TransactionType: string
{
    case PURCHASE = 'purchase';
    case E_COM = 'e_com';
    case P2P_IN = 'p2p_in';
    case TOP_UP = 'top_up';
    case P2P_OUT = 'p2p_out';
    case ATM = 'atm';
    case REFUND = 'refund';
    case DECLINED_FUNDS = 'declined_funds';
    case UNKNOWN = 'unknown';

    public function title(Locale $locale): string
    {
        return match ($locale) {
            Locale::UZ => match ($this) {
                self::PURCHASE       => 'Xarid 🛒',
                self::E_COM          => 'Onlayn toʻlov 🌐',
                self::P2P_IN         => 'Kirim oʻtkazma 📥',
                self::TOP_UP         => 'Hisobni toʻldirish 📥',
                self::P2P_OUT        => 'Chiqim / Oʻtkazma 📤',
                self::ATM            => 'Bankomatdan naqd pul 🏧',
                self::REFUND         => 'Xarid bekor qilindi 🔄',
                self::DECLINED_FUNDS => '❌ Rad etildi: Mablagʻ yetarli emas',
                self::UNKNOWN        => 'Tranzaksiya 💳',
            },
            Locale::EN => match ($this) {
                self::PURCHASE       => 'Purchase 🛒',
                self::E_COM          => 'Online payment 🌐',
                self::P2P_IN         => 'Incoming transfer 📥',
                self::TOP_UP         => 'Account top-up 📥',
                self::P2P_OUT        => 'Outgoing transfer 📤',
                self::ATM            => 'ATM withdrawal 🏧',
                self::REFUND         => 'Refund / Reversal 🔄',
                self::DECLINED_FUNDS => '❌ Declined: Insufficient funds',
                self::UNKNOWN        => 'Transaction 💳',
            },
            Locale::RU => match ($this) {
                self::PURCHASE       => 'Покупка 🛒',
                self::E_COM          => 'Онлайн-оплата 🌐',
                self::P2P_IN         => 'Входящий перевод 📥',
                self::TOP_UP         => 'Пополнение счёта 📥',
                self::P2P_OUT        => 'Исходящий перевод / списание 📤',
                self::ATM            => 'Снятие наличных в банкомате 🏧',
                self::REFUND         => 'Отмена покупки 🔄',
                self::DECLINED_FUNDS => '❌ Отказ: Недостаточно средств',
                self::UNKNOWN        => 'Транзакция 💳',
            },
        };
    }
}
