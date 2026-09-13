<?php

namespace App\Services\Finance;

use App\Enums\FinancialMovementDirection;
use App\Enums\FinancialTransactionStatus;
use App\Models\Area;
use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use Illuminate\Support\Collection;

class FinancialBalanceService
{
    public function forAccount(FinancialAccount|string $account): string
    {
        $accountId = $account instanceof FinancialAccount ? $account->id : $account;

        return $this->forAccountIds([$accountId])->get($accountId, '0.00');
    }

    /**
     * @param  array<int, string>  $accountIds
     * @return Collection<string, string>
     */
    public function forAccountIds(array $accountIds): Collection
    {
        if ($accountIds === []) {
            return collect();
        }

        $balances = FinancialMovement::query()
            ->join('financial_transactions', 'financial_transactions.id', '=', 'financial_movements.financial_transaction_id')
            ->whereIn('financial_account_id', $accountIds)
            ->where('financial_transactions.status', FinancialTransactionStatus::Settled->value)
            ->whereNotNull('financial_movements.settled_on')
            ->groupBy('financial_movements.financial_account_id')
            ->select('financial_movements.financial_account_id')
            ->selectRaw(
                'SUM(CASE WHEN financial_movements.direction = ? THEN financial_movements.amount ELSE -financial_movements.amount END) AS balance',
                [FinancialMovementDirection::Inflow->value],
            )
            ->pluck('balance', 'financial_account_id');

        return collect($accountIds)->mapWithKeys(
            fn (string $accountId): array => [$accountId => (string) ($balances->get($accountId) ?? '0.00')],
        );
    }

    public function consolidatedForArea(Area|string $area): string
    {
        $areaId = $area instanceof Area ? $area->id : $area;

        $balance = FinancialMovement::query()
            ->join('financial_transactions', 'financial_transactions.id', '=', 'financial_movements.financial_transaction_id')
            ->join('financial_accounts', 'financial_accounts.id', '=', 'financial_movements.financial_account_id')
            ->leftJoin('churches', 'churches.id', '=', 'financial_accounts.church_id')
            ->where(fn ($query) => $query
                ->where('financial_accounts.area_id', $areaId)
                ->orWhere('churches.area_id', $areaId))
            ->where('financial_transactions.status', FinancialTransactionStatus::Settled->value)
            ->whereNotNull('financial_movements.settled_on')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN direction = ? THEN financial_movements.amount ELSE -financial_movements.amount END), 0) AS balance',
                [FinancialMovementDirection::Inflow->value],
            )
            ->value('balance');

        return (string) ($balance ?? '0.00');
    }
}
