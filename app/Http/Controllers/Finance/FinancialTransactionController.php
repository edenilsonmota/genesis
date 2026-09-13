<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FinancialMovementDirection;
use App\Enums\FinancialPaymentMethod;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinancialTransaction\CancelFinancialTransactionRequest;
use App\Http\Requests\Finance\FinancialTransaction\IndexFinancialTransactionRequest;
use App\Http\Requests\Finance\FinancialTransaction\ReverseFinancialTransactionRequest;
use App\Http\Requests\Finance\FinancialTransaction\SettleFinancialTransactionRequest;
use App\Http\Requests\Finance\FinancialTransaction\StoreExpenseRequest;
use App\Http\Requests\Finance\FinancialTransaction\StoreIncomeRequest;
use App\Http\Requests\Finance\FinancialTransaction\StoreTransferRequest;
use App\Http\Requests\Finance\FinancialTransaction\UpdateFinancialTransactionRequest;
use App\Models\Area;
use App\Models\Church;
use App\Models\Department;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\User;
use App\PermissionLevel;
use App\Services\Finance\FinancialBalanceService;
use App\Services\Finance\FinancialTransactionService;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FinancialTransactionController extends Controller
{
    public function index(
        IndexFinancialTransactionRequest $request,
        PermissionService $permissions,
    ): View {
        $actor = $request->user();
        $filters = $request->validated();
        if (! array_key_exists('date_from', $filters) && ! array_key_exists('date_to', $filters) && ! ($filters['all_periods'] ?? false)) {
            $filters['date_from'] = today()->startOfMonth()->toDateString();
            $filters['date_to'] = today()->endOfMonth()->toDateString();
            $filters['default_period'] = true;
        }

        $accounts = $this->accessibleAccounts($actor, $permissions, PermissionLevel::Read);
        $accountIds = $accounts->pluck('id')->all();
        $query = $this->filteredQuery($filters, $accountIds);
        $summary = $this->summaryForQuery(clone $query);
        $transactions = $query
            ->with([
                'category:id,name,type',
                'department:id,name',
                'responsibleMember:id,name',
                'createdByUser:id,display_name,username',
                'movements.account.area:id,name',
                'movements.account.church:id,name,area_id',
                'reversalTransaction:id,reversal_of_transaction_id',
            ])
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $areas = $accounts->pluck('area')->filter()->unique('id')->values();
        $churches = $accounts->pluck('church')->filter()->unique('id')->sortBy('name')->values();
        $areaIds = $accounts->map(fn (FinancialAccount $account): ?string => $account->area_id ?? $account->church?->area_id)->filter()->unique()->values();

        return view('finance.transactions.index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'summary' => $summary,
            'accounts' => $accounts,
            'areas' => $areas,
            'churches' => $churches,
            'categories' => FinancialCategory::query()->whereIn('area_id', $areaIds)->orderBy('name')->get(['id', 'name', 'type']),
            'departments' => Department::query()->whereIn('area_id', $areaIds)->orderBy('name')->get(['id', 'name']),
            'responsibleMembers' => Member::query()->whereIn('id', (clone $query)->whereNotNull('responsible_member_id')->select('responsible_member_id'))->orderBy('name')->get(['id', 'name']),
            'transactionTypes' => FinancialTransactionType::cases(),
            'transactionStatuses' => FinancialTransactionStatus::cases(),
            'canWriteFinancialTransactions' => Gate::allows('create', FinancialTransaction::class),
        ]);
    }

    public function create(
        PermissionService $permissions,
        FinancialBalanceService $balances,
    ): View|RedirectResponse {
        Gate::authorize('create', FinancialTransaction::class);
        $type = FinancialTransactionType::tryFrom((string) request()->query('type', 'income'));
        if ($type === null || $type === FinancialTransactionType::Reversal) {
            $type = FinancialTransactionType::Income;
        }

        $options = $this->formOptions(request()->user(), $permissions, $balances);
        if (! Area::query()->where('status', Status::Active->value)->exists()) {
            return $this->redirectToPrerequisite('Cadastre e ative a área antes de registrar movimentações financeiras.');
        }

        return view('finance.transactions.create', [
            ...$options,
            'selectedType' => $type,
            'paymentMethods' => FinancialPaymentMethod::cases(),
        ]);
    }

    public function storeIncome(
        StoreIncomeRequest $request,
        FinancialTransactionService $service,
    ): RedirectResponse {
        $transaction = $service->createIncome($request->validated(), $request->user());

        return redirect()->route('finance.transactions.show', $transaction)
            ->with('success', 'Entrada registrada com sucesso.');
    }

    public function storeExpense(
        StoreExpenseRequest $request,
        FinancialTransactionService $service,
        FinancialBalanceService $balances,
    ): RedirectResponse {
        $transaction = $service->createExpense($request->validated(), $request->user());
        $response = redirect()->route('finance.transactions.show', $transaction)
            ->with('success', 'Saída registrada com sucesso.');

        return $transaction->status === FinancialTransactionStatus::Settled
            && (float) $balances->forAccount($transaction->movements->first()->financial_account_id) < 0
                ? $response->with('warning', 'A saída foi liquidada e a conta ficou com saldo negativo. O lançamento foi mantido.')
                : $response;
    }

    public function storeTransfer(
        StoreTransferRequest $request,
        FinancialTransactionService $service,
    ): RedirectResponse {
        $transaction = $service->createTransfer($request->validated(), $request->user());

        return redirect()->route('finance.transactions.show', $transaction)
            ->with('success', 'Transferência registrada com sucesso.');
    }

    public function show(FinancialTransaction $financialTransaction): View
    {
        Gate::authorize('view', $financialTransaction);
        $financialTransaction->load([
            'category', 'department', 'responsibleMember', 'createdByUser', 'updatedByUser',
            'cancelledByUser', 'reversedByUser', 'reversalOfTransaction', 'reversalTransaction',
            'movements.account.area', 'movements.account.church',
        ]);

        return view('finance.transactions.show', compact('financialTransaction'));
    }

    public function edit(
        FinancialTransaction $financialTransaction,
        PermissionService $permissions,
        FinancialBalanceService $balances,
    ): View {
        Gate::authorize('update', $financialTransaction);
        $financialTransaction->load('movements.account');

        return view('finance.transactions.edit', [
            ...$this->formOptions(request()->user(), $permissions, $balances),
            'financialTransaction' => $financialTransaction,
            'selectedType' => $financialTransaction->type,
            'paymentMethods' => FinancialPaymentMethod::cases(),
        ]);
    }

    public function update(
        UpdateFinancialTransactionRequest $request,
        FinancialTransaction $financialTransaction,
        FinancialTransactionService $service,
    ): RedirectResponse {
        $service->update($financialTransaction, $request->validated(), $request->user());

        return redirect()->route('finance.transactions.show', $financialTransaction)
            ->with('success', 'Movimentação atualizada com sucesso.');
    }

    public function settle(
        SettleFinancialTransactionRequest $request,
        FinancialTransaction $financialTransaction,
        FinancialTransactionService $service,
        FinancialBalanceService $balances,
    ): RedirectResponse {
        $transaction = $service->settle(
            $financialTransaction,
            $request->string('settled_on')->toString(),
            $request->user(),
        );
        $response = back()->with('success', 'Movimentação liquidada com sucesso.');

        return $transaction->type === FinancialTransactionType::Expense
            && (float) $balances->forAccount($transaction->movements->first()->financial_account_id) < 0
                ? $response->with('warning', 'A conta ficou com saldo negativo. A liquidação foi mantida.')
                : $response;
    }

    public function cancel(
        CancelFinancialTransactionRequest $request,
        FinancialTransaction $financialTransaction,
        FinancialTransactionService $service,
    ): RedirectResponse {
        $service->cancel($financialTransaction, $request->string('reason')->toString(), $request->user());

        return back()->with('success', 'Movimentação cancelada e preservada no histórico.');
    }

    public function reverse(
        ReverseFinancialTransactionRequest $request,
        FinancialTransaction $financialTransaction,
        FinancialTransactionService $service,
    ): RedirectResponse {
        $reversal = $service->reverse($financialTransaction, $request->string('reason')->toString(), $request->user());

        return redirect()->route('finance.transactions.show', $reversal)
            ->with('success', 'Estorno realizado. Os movimentos originais foram preservados.');
    }

    /** @param array<string, mixed> $filters
     * @param array<int, string> $accountIds
     */
    private function filteredQuery(array $filters, array $accountIds): Builder
    {
        return FinancialTransaction::query()
            ->whereHas('movements', fn (Builder $query): Builder => $query->whereIn('financial_account_id', $accountIds))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->where(fn (Builder $query): Builder => $query
                ->where('title', 'ILIKE', "%{$search}%")
                ->orWhere('description', 'ILIKE', "%{$search}%")
                ->orWhere('counterparty_name', 'ILIKE', "%{$search}%")
                ->orWhere('document_number', 'ILIKE', "%{$search}%")))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_on', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_on', '<=', $date))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['account_id'] ?? null, fn (Builder $query, string $accountId): Builder => $query
                ->whereHas('movements', fn (Builder $query): Builder => $query->where('financial_account_id', $accountId)))
            ->when($filters['scope_type'] ?? null, function (Builder $query, string $scopeType) use ($filters): Builder {
                $scopeId = $filters['scope_id'] ?? null;
                if ($scopeId === null) {
                    return $query;
                }

                return $query->whereHas('movements.account', fn (Builder $query): Builder => $scopeType === 'area'
                    ? $query->where('area_id', $scopeId)
                    : $query->where('church_id', $scopeId));
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('category_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('department_id', $id))
            ->when($filters['responsible_member_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('responsible_member_id', $id));
    }

    /** @return array{inflows: string, outflows: string, result: string, transferred: string} */
    private function summaryForQuery(Builder $query): array
    {
        $transactionIds = (clone $query)->select('financial_transactions.id');
        $movementTotals = FinancialMovement::query()
            ->join('financial_transactions', 'financial_transactions.id', '=', 'financial_movements.financial_transaction_id')
            ->whereIn('financial_movements.financial_transaction_id', $transactionIds)
            ->where('financial_transactions.status', FinancialTransactionStatus::Settled->value)
            ->where('financial_transactions.type', '!=', FinancialTransactionType::Transfer->value)
            ->whereNotNull('financial_movements.settled_on')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN financial_movements.direction = ? THEN financial_movements.amount ELSE 0 END), 0) AS inflows',
                [FinancialMovementDirection::Inflow->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN financial_movements.direction = ? THEN financial_movements.amount ELSE 0 END), 0) AS outflows',
                [FinancialMovementDirection::Outflow->value],
            )
            ->first();
        $inflows = (string) ($movementTotals?->inflows ?? '0.00');
        $outflows = (string) ($movementTotals?->outflows ?? '0.00');

        return [
            'inflows' => $inflows,
            'outflows' => $outflows,
            'result' => number_format((float) $inflows - (float) $outflows, 2, '.', ''),
            'transferred' => (string) ((clone $query)
                ->where('status', FinancialTransactionStatus::Settled->value)
                ->where('type', FinancialTransactionType::Transfer->value)
                ->sum('amount') ?: '0.00'),
        ];
    }

    /** @return Collection<int, FinancialAccount> */
    private function accessibleAccounts(User $actor, PermissionService $permissions, PermissionLevel $level, bool $activeOnly = false): Collection
    {
        $query = FinancialAccount::query()->with(['area:id,name', 'church:id,name,area_id']);
        if (! $actor->isGlobalAdministrator()) {
            $churchIds = $permissions->churchesWithPermission($actor, 'finance.transactions', $level)->pluck('id');
            $query->whereIn('church_id', $churchIds)->whereNull('area_id');
        }
        if ($activeOnly) {
            $query->where('status', Status::Active->value);
        }

        return $query->orderBy('name')->get();
    }

    /** @return array<string, mixed> */
    private function formOptions(User $actor, PermissionService $permissions, FinancialBalanceService $balances): array
    {
        $accounts = $this->accessibleAccounts($actor, $permissions, PermissionLevel::Write, activeOnly: true);
        $areaIds = $accounts
            ->map(fn (FinancialAccount $account): ?string => $account->area_id ?? $account->church?->area_id)
            ->filter()
            ->unique()
            ->values();
        $churchIds = $accounts->pluck('church_id')->filter()->unique()->values();
        $responsibleMembers = Member::query()
            ->where('status', Status::Active->value)
            ->whereHas('memberships', fn (Builder $query): Builder => $query
                ->effectiveOn(today()->toDateString())
                ->whereHas('church', fn (Builder $query): Builder => $query->whereIn('area_id', $areaIds)))
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'accounts' => $accounts,
            'areaIds' => $areaIds,
            'churchIds' => $churchIds,
            'categories' => FinancialCategory::query()->active()->whereIn('area_id', $areaIds)->orderBy('name')->get(),
            'departments' => Department::query()->active()->whereIn('area_id', $areaIds)->orderBy('name')->get(),
            'responsibleMembers' => $responsibleMembers,
            'accountBalances' => $balances->forAccountIds($accounts->pluck('id')->all()),
        ];
    }

    private function redirectToPrerequisite(string $message): RedirectResponse
    {
        $actor = request()->user();
        $route = $actor->isGlobalAdministrator()
            ? route('organization.index', ['panel' => 'create-area'])
            : route('finance.transactions.index');

        return redirect($route)->withErrors(['area' => $message]);
    }
}
