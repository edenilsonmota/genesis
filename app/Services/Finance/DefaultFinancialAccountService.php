<?php

namespace App\Services\Finance;

use App\Enums\FinancialAccountType;
use App\Models\Area;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Status;

class DefaultFinancialAccountService
{
    public const AREA_ACCOUNT_NAME = 'CAIXA DA ÁREA';

    public const CHURCH_ACCOUNT_NAME = 'CAIXA DA IGREJA';

    /**
     * Garante o caixa operacional da área sem criar uma segunda conta padrão.
     */
    public function ensureForArea(Area $area): FinancialAccount
    {
        return FinancialAccount::query()->firstOrCreate(
            [
                'area_id' => $area->id,
                'church_id' => null,
                'is_default' => true,
            ],
            [
                'name' => self::AREA_ACCOUNT_NAME,
                'type' => FinancialAccountType::Cash,
                'status' => Status::Active,
            ],
        );
    }

    /**
     * Garante o caixa operacional de cada igreja sem criar uma segunda conta padrão.
     */
    public function ensureForChurch(Church $church): FinancialAccount
    {
        return FinancialAccount::query()->firstOrCreate(
            [
                'area_id' => null,
                'church_id' => $church->id,
                'is_default' => true,
            ],
            [
                'name' => self::CHURCH_ACCOUNT_NAME,
                'type' => FinancialAccountType::Cash,
                'status' => Status::Active,
            ],
        );
    }
}
