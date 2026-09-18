<?php

namespace App\Enums;

enum MemberImportRowStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Processed = 'processed';
    case Failed = 'failed';
}
