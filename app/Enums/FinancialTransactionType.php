<?php

namespace App\Enums;

enum FinancialTransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Entrada',
            self::Expense => 'Saída',
            self::Transfer => 'Transferência',
            self::Reversal => 'Estorno',
        };
    }
}
