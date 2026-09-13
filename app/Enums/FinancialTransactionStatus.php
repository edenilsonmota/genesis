<?php

namespace App\Enums;

enum FinancialTransactionStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Pending => 'Pendente',
            self::Settled => 'Liquidado',
            self::Cancelled => 'Cancelado',
        };
    }
}
