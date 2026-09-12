<?php

namespace App\Enums;

enum FinancialAccountType: string
{
    case Cash = 'cash';
    case Checking = 'checking';
    case Savings = 'savings';
    case DigitalWallet = 'digital_wallet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Caixa',
            self::Checking => 'Conta corrente',
            self::Savings => 'Conta poupança',
            self::DigitalWallet => 'Carteira digital',
            self::Other => 'Outro',
        };
    }
}
