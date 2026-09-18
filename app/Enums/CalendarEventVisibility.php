<?php

namespace App\Enums;

enum CalendarEventVisibility: string
{
    case Church = 'church';
    case Department = 'department';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Church => 'Igreja',
            self::Department => 'Departamento',
            self::Private => 'Privado',
        };
    }
}
