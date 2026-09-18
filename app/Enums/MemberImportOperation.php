<?php

namespace App\Enums;

enum MemberImportOperation: string
{
    case Create = 'create';
    case Update = 'update';

    public function label(): string
    {
        return $this === self::Create ? 'Novo membro' : 'Atualização';
    }
}
