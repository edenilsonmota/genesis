<?php

namespace App\Enums;

enum CalendarEventStatus: string
{
    case Confirmed = 'confirmed';
    case Draft = 'draft';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Confirmed => 'Confirmado',
            self::Draft => 'Rascunho',
            self::Cancelled => 'Cancelado',
        };
    }
}
