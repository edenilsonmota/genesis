<?php

namespace App\Enums;

enum FinancialTransactionOrigin: string
{
    case Manual = 'manual';
    case Tithe = 'tithe';
    case System = 'system';
}
