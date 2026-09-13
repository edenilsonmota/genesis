<?php

namespace App\Enums;

enum FinancialPaymentMethod: string
{
    case Cash = 'cash';
    case Pix = 'pix';
    case BankTransfer = 'bank_transfer';
    case DebitCard = 'debit_card';
    case CreditCard = 'credit_card';
    case Check = 'check';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Dinheiro',
            self::Pix => 'Pix',
            self::BankTransfer => 'Transferência bancária',
            self::DebitCard => 'Cartão de débito',
            self::CreditCard => 'Cartão de crédito',
            self::Check => 'Cheque',
            self::Other => 'Outro',
        };
    }
}
