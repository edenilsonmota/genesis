<?php

namespace App\Enums;

enum FinancialMovementDirection: string
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';

    public function label(): string
    {
        return match ($this) {
            self::Inflow => 'Entrada',
            self::Outflow => 'Saída',
        };
    }

    public function opposite(): self
    {
        return $this === self::Inflow ? self::Outflow : self::Inflow;
    }
}
