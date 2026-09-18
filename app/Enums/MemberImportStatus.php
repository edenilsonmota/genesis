<?php

namespace App\Enums;

enum MemberImportStatus: string
{
    case Uploaded = 'uploaded';
    case Validating = 'validating';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Processing = 'processing';
    case Completed = 'completed';
    case ValidationFailed = 'validation_failed';
    case ProcessingFailed = 'processing_failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Arquivo enviado',
            self::Validating => 'Validando arquivo',
            self::AwaitingConfirmation => 'Aguardando confirmação',
            self::Processing => 'Processando importação',
            self::Completed => 'Importação concluída',
            self::ValidationFailed => 'Importação com erros',
            self::ProcessingFailed => 'Falha no processamento',
        };
    }
}
