<?php

namespace App\Services\Finance;

use App\Enums\FinancialMovementDirection;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Models\Church;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinancialOverviewService
{
    /** @return array<string, mixed> */
    public function forScope(?Church $church, int $year): array
    {
        $scope = $church?->id ?? 'overview';
        $version = (int) Cache::get('finance-overview:version', 1);

        return Cache::remember(
            "finance-overview:v{$version}:{$scope}:{$year}",
            now()->addSeconds((int) config('genesis.finance.overview_cache_seconds', 300)),
            fn (): array => $this->build($church, $year),
        );
    }

    /** @return array<string, mixed> */
    private function build(?Church $church, int $year): array
    {
        $months = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        $operating = $this->movements($church)
            ->whereYear('fm.settled_on', $year)
            ->where('ft.type', '!=', FinancialTransactionType::Transfer->value);
        $monthlyRows = (clone $operating)
            ->selectRaw("DATE_PART('month', fm.settled_on)::int AS month")
            ->selectRaw('SUM(CASE WHEN fm.direction = ? THEN fm.amount ELSE 0 END) AS inflows', [FinancialMovementDirection::Inflow->value])
            ->selectRaw('SUM(CASE WHEN fm.direction = ? THEN fm.amount ELSE 0 END) AS outflows', [FinancialMovementDirection::Outflow->value])
            ->groupByRaw("DATE_PART('month', fm.settled_on)::int")
            ->get()->keyBy('month');

        $inflows = $this->monthSeries($monthlyRows, 'inflows');
        $outflows = $this->monthSeries($monthlyRows, 'outflows');
        $results = array_map(fn (float $in, float $out): float => $in - $out, $inflows, $outflows);

        $opening = (float) $this->movements($church)->whereDate('fm.settled_on', '<', "{$year}-01-01")
            ->selectRaw('COALESCE(SUM(CASE WHEN fm.direction = ? THEN fm.amount ELSE -fm.amount END), 0) AS balance', [FinancialMovementDirection::Inflow->value])
            ->value('balance');
        $balanceRows = $this->movements($church)->whereYear('fm.settled_on', $year)
            ->selectRaw("DATE_PART('month', fm.settled_on)::int AS month")
            ->selectRaw('SUM(CASE WHEN fm.direction = ? THEN fm.amount ELSE -fm.amount END) AS net', [FinancialMovementDirection::Inflow->value])
            ->groupByRaw("DATE_PART('month', fm.settled_on)::int")->get()->keyBy('month');
        $running = $opening;
        $balances = [];
        foreach (range(1, 12) as $month) {
            $running += (float) ($balanceRows->get($month)?->net ?? 0);
            $balances[] = round($running, 2);
        }

        $incomeCategories = $this->categoryTotals($operating, FinancialMovementDirection::Inflow);
        $expenseCategories = $this->categoryTotals($operating, FinancialMovementDirection::Outflow);
        $accounts = $this->accountBalances($church);
        $churches = $this->churchTotals($church, $year);
        $departments = $this->departmentTotals($operating);
        $previous = $this->annualResults($church, $year - 1);
        $pending = $this->pending($church);

        return [
            'scope' => $church?->name ?? 'Visão geral',
            'year' => $year,
            'months' => $months,
            'summary' => [
                'balance' => round((float) ($accounts->sum('value')), 2),
                'inflows' => round(array_sum($inflows), 2),
                'outflows' => round(array_sum($outflows), 2),
                'result' => round(array_sum($results), 2),
            ],
            'monthly' => compact('inflows', 'outflows', 'results'),
            'balances' => $balances,
            'income_categories' => $incomeCategories,
            'expense_categories' => $expenseCategories,
            'accounts' => $accounts->values()->all(),
            'churches' => $churches,
            'departments' => $departments,
            'annual_comparison' => ['current' => $results, 'previous' => $previous],
            'pending' => $pending,
        ];
    }

    private function movements(?Church $church): Builder
    {
        return DB::table('financial_movements as fm')
            ->join('financial_transactions as ft', 'ft.id', '=', 'fm.financial_transaction_id')
            ->join('financial_accounts as fa', 'fa.id', '=', 'fm.financial_account_id')
            ->leftJoin('churches as ch', 'ch.id', '=', 'fa.church_id')
            ->where('ft.status', FinancialTransactionStatus::Settled->value)
            ->whereNotNull('fm.settled_on')
            ->when($church, fn (Builder $query): Builder => $query->where('fa.church_id', $church->id));
    }

    /** @param Collection<int|string, object> $rows
     * @return list<float>
     */
    private function monthSeries($rows, string $field): array
    {
        return array_map(fn (int $month): float => round((float) ($rows->get($month)?->{$field} ?? 0), 2), range(1, 12));
    }

    /** @return list<array{name: string, value: float}> */
    private function categoryTotals(Builder $query, FinancialMovementDirection $direction): array
    {
        return (clone $query)->leftJoin('financial_categories as fc', 'fc.id', '=', 'ft.category_id')
            ->where('fm.direction', $direction->value)
            ->selectRaw("COALESCE(fc.name, 'SEM CATEGORIA') AS name, SUM(fm.amount) AS value")
            ->groupByRaw("COALESCE(fc.name, 'SEM CATEGORIA')")
            ->orderByDesc('value')->limit(10)->get()
            ->map(fn (object $row): array => ['name' => $row->name, 'value' => round((float) $row->value, 2)])->all();
    }

    /** @return Collection<int, array{name: string, value: float}> */
    private function accountBalances(?Church $church)
    {
        return $this->movements($church)
            ->selectRaw('fa.id, fa.name, SUM(CASE WHEN fm.direction = ? THEN fm.amount ELSE -fm.amount END) AS value', [FinancialMovementDirection::Inflow->value])
            ->groupBy('fa.id', 'fa.name')->orderBy('fa.name')->get()
            ->map(fn (object $row): array => ['name' => $row->name, 'value' => round((float) $row->value, 2)]);
    }

    /** @return list<array{name: string, inflows: float, outflows: float, balance: float}> */
    private function churchTotals(?Church $church, int $year): array
    {
        return $this->movements($church)->whereYear('fm.settled_on', $year)->whereNotNull('fa.church_id')
            ->where('ft.type', '!=', FinancialTransactionType::Transfer->value)
            ->selectRaw('ch.name')
            ->selectRaw('SUM(CASE WHEN fm.direction = ? THEN fm.amount ELSE 0 END) AS inflows', [FinancialMovementDirection::Inflow->value])
            ->selectRaw('SUM(CASE WHEN fm.direction = ? THEN fm.amount ELSE 0 END) AS outflows', [FinancialMovementDirection::Outflow->value])
            ->groupBy('ch.id', 'ch.name')->orderBy('ch.name')->get()
            ->map(fn (object $row): array => ['name' => $row->name, 'inflows' => round((float) $row->inflows, 2), 'outflows' => round((float) $row->outflows, 2), 'balance' => round((float) $row->inflows - (float) $row->outflows, 2)])->all();
    }

    /** @return list<array{name: string, value: float}> */
    private function departmentTotals(Builder $query): array
    {
        return (clone $query)->join('departments as d', 'd.id', '=', 'ft.department_id')
            ->where('fm.direction', FinancialMovementDirection::Outflow->value)
            ->selectRaw('d.name, SUM(fm.amount) AS value')->groupBy('d.id', 'd.name')->orderByDesc('value')->limit(10)->get()
            ->map(fn (object $row): array => ['name' => $row->name, 'value' => round((float) $row->value, 2)])->all();
    }

    /** @return list<float> */
    private function annualResults(?Church $church, int $year): array
    {
        $rows = $this->movements($church)->whereYear('fm.settled_on', $year)->where('ft.type', '!=', FinancialTransactionType::Transfer->value)
            ->selectRaw("DATE_PART('month', fm.settled_on)::int AS month")
            ->selectRaw('SUM(CASE WHEN fm.direction = ? THEN fm.amount ELSE -fm.amount END) AS result', [FinancialMovementDirection::Inflow->value])
            ->groupByRaw("DATE_PART('month', fm.settled_on)::int")->get()->keyBy('month');

        return $this->monthSeries($rows, 'result');
    }

    /** @return array{count: int, amount: float} */
    private function pending(?Church $church): array
    {
        $query = DB::table('financial_transactions as ft')->where('ft.status', FinancialTransactionStatus::Pending->value)
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')->from('financial_movements as fm')->join('financial_accounts as fa', 'fa.id', '=', 'fm.financial_account_id')->whereColumn('fm.financial_transaction_id', 'ft.id')->when($church, fn (Builder $query): Builder => $query->where('fa.church_id', $church->id)));

        return ['count' => $query->count(), 'amount' => round((float) (clone $query)->sum('ft.amount'), 2)];
    }
}
