<?php

namespace App\Services\Finance;

use App\Enums\FinancialMovementDirection;
use App\Enums\FinancialTransactionOrigin;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Models\Department;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialMovement;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\User;
use App\PermissionLevel;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialTransactionService
{
    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
    ) {}

    /** @param array<string, mixed> $data */
    public function createIncome(array $data, User $actor): FinancialTransaction
    {
        return $this->createSingleMovement($data, $actor, FinancialTransactionType::Income);
    }

    /** @param array<string, mixed> $data */
    public function createExpense(array $data, User $actor): FinancialTransaction
    {
        return $this->createSingleMovement($data, $actor, FinancialTransactionType::Expense);
    }

    /** @param array<string, mixed> $data */
    public function createTithe(array $data, User $actor): FinancialTransaction
    {
        return $this->createSingleMovement(
            $data,
            $actor,
            FinancialTransactionType::Income,
            FinancialTransactionOrigin::Tithe,
            'finance.tithes',
            (string) $data['member_id'],
        );
    }

    /** @param array{amount: string, competence_month_number: int, competence_year: int, payment_method: string, description: ?string} $data */
    public function updateTitheDetails(FinancialTransaction $financialTransaction, array $data, User $actor): FinancialTransaction
    {
        return $this->execute('update_tithe_details', $financialTransaction, function () use ($financialTransaction, $data, $actor): FinancialTransaction {
            $transaction = FinancialTransaction::query()->lockForUpdate()->findOrFail($financialTransaction->id);
            $transaction->load(['movements' => fn ($query) => $query->lockForUpdate(), 'movements.account']);

            if ($transaction->origin !== FinancialTransactionOrigin::Tithe || $transaction->movements->count() !== 1) {
                throw ValidationException::withMessages(['financial_transaction' => 'Os detalhes só podem ser alterados em um dízimo válido.']);
            }

            $account = $transaction->movements->first()->account;
            $this->assertAccountCanBeUsed($actor, $account, 'financial_transaction', requireActive: false, permissionModule: 'finance.tithes');
            $before = ['amount' => $transaction->amount, 'competence_month' => $transaction->competence_month?->toDateString(), 'payment_method' => $transaction->payment_method?->value, 'description' => $transaction->description];
            $competenceMonth = sprintf('%d-%02d-01', $data['competence_year'], $data['competence_month_number']);
            $transaction->movements->first()->update(['amount' => $data['amount']]);
            $transaction->update([
                'amount' => $data['amount'],
                'competence_month' => $competenceMonth,
                'payment_method' => $data['payment_method'],
                'description' => $data['description'],
                'updated_by_user_id' => $actor->id,
            ]);
            $this->recordAudit('financial_tithe.details_updated', $transaction->load('movements'), $account, [
                'before' => $before,
                'after' => ['amount' => $transaction->amount, 'competence_month' => $transaction->competence_month?->toDateString(), 'payment_method' => $transaction->payment_method?->value, 'description' => $transaction->description],
            ]);

            return $transaction;
        });
    }

    /** @param array<string, mixed> $data */
    public function createTransfer(array $data, User $actor): FinancialTransaction
    {
        return $this->execute('create_transfer', null, function () use ($data, $actor): FinancialTransaction {
            $accounts = $this->lockedAccounts([
                (string) $data['source_account_id'],
                (string) $data['destination_account_id'],
            ]);
            $source = $accounts->firstWhere('id', (string) $data['source_account_id']);
            $destination = $accounts->firstWhere('id', (string) $data['destination_account_id']);

            if ($source === null || $destination === null) {
                throw ValidationException::withMessages(['source_account_id' => 'Selecione contas financeiras válidas.']);
            }
            if ($source->id === $destination->id) {
                throw ValidationException::withMessages(['destination_account_id' => 'A conta de destino deve ser diferente da conta de origem.']);
            }

            $this->assertAccountCanBeUsed($actor, $source, 'source_account_id');
            $this->assertAccountCanBeUsed($actor, $destination, 'destination_account_id');

            $transaction = $this->createTransaction($data, $actor, FinancialTransactionType::Transfer, null);
            $settledOn = $transaction->status === FinancialTransactionStatus::Settled
                ? $transaction->occurred_on->toDateString()
                : null;

            $this->createMovement($transaction, $source, FinancialMovementDirection::Outflow, $settledOn);
            $this->createMovement($transaction, $destination, FinancialMovementDirection::Inflow, $settledOn);
            $this->assertMovementStructure($transaction->load('movements'));
            $this->recordAudit('financial_transaction.transfer_created', $transaction, $source, [
                'destination_scope' => $this->scopeForAccount($destination),
                'destination_account_id' => $destination->id,
            ]);

            return $transaction->load('movements.account');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(FinancialTransaction $financialTransaction, array $data, User $actor): FinancialTransaction
    {
        return $this->execute('update', $financialTransaction, function () use ($financialTransaction, $data, $actor): FinancialTransaction {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($financialTransaction->id);
            if (! $locked->isEditable()) {
                throw ValidationException::withMessages(['financial_transaction' => 'Somente lançamentos manuais em rascunho ou pendentes podem ser editados.']);
            }

            $before = $this->auditSnapshot($locked->load('movements'));
            if ($locked->type === FinancialTransactionType::Transfer) {
                $this->updateTransfer($locked, $data, $actor);
            } else {
                $this->updateSingleMovement($locked, $data, $actor);
            }

            $locked->update($this->transactionAttributes($data, $actor, forUpdate: true));
            $locked->refresh()->load('movements.account');
            $this->assertMovementStructure($locked);
            $account = $locked->movements->first()->account;
            $this->recordAudit('financial_transaction.updated', $locked, $account, [
                'before' => $before,
                'after' => $this->auditSnapshot($locked),
            ]);

            return $locked;
        });
    }

    public function settle(FinancialTransaction $financialTransaction, string $settledOn, User $actor): FinancialTransaction
    {
        return $this->execute('settle', $financialTransaction, function () use ($financialTransaction, $settledOn, $actor): FinancialTransaction {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($financialTransaction->id);
            $locked->load(['movements' => fn ($query) => $query->lockForUpdate(), 'movements.account.church']);

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages(['financial_transaction' => 'Este lançamento não pode ser liquidado no estado atual.']);
            }
            if ($locked->movements->contains(fn (FinancialMovement $movement): bool => $movement->settled_on !== null)) {
                throw ValidationException::withMessages(['financial_transaction' => 'Este lançamento já possui movimento liquidado.']);
            }

            $this->revalidateForSettlement($locked, $actor);
            $before = $locked->status;
            FinancialMovement::query()
                ->where('financial_transaction_id', $locked->id)
                ->update(['settled_on' => $settledOn, 'updated_at' => now()]);
            $locked->update([
                'status' => FinancialTransactionStatus::Settled,
                'updated_by_user_id' => $actor->id,
            ]);
            $locked->refresh()->load('movements.account');
            $this->assertMovementStructure($locked);
            $this->recordAudit('financial_transaction.settled', $locked, $locked->movements->first()->account, [
                'status_before' => $before->value,
                'status_after' => $locked->status->value,
                'settled_on' => $settledOn,
            ]);

            return $locked;
        });
    }

    public function cancel(FinancialTransaction $financialTransaction, string $reason, User $actor): FinancialTransaction
    {
        return $this->execute('cancel', $financialTransaction, function () use ($financialTransaction, $reason, $actor): FinancialTransaction {
            $locked = FinancialTransaction::query()->lockForUpdate()->findOrFail($financialTransaction->id);
            $locked->load(['movements' => fn ($query) => $query->lockForUpdate(), 'movements.account']);

            if (! $locked->isEditable()) {
                throw ValidationException::withMessages(['financial_transaction' => 'Somente rascunhos e pendências podem ser cancelados.']);
            }
            if ($locked->movements->contains(fn (FinancialMovement $movement): bool => $movement->settled_on !== null)) {
                throw ValidationException::withMessages(['financial_transaction' => 'Um lançamento com movimento liquidado não pode ser cancelado.']);
            }

            $before = $locked->status;
            $locked->update([
                'status' => FinancialTransactionStatus::Cancelled,
                'updated_by_user_id' => $actor->id,
                'cancelled_by_user_id' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
            $this->recordAudit('financial_transaction.cancelled', $locked, $locked->movements->first()->account, [
                'status_before' => $before->value,
                'status_after' => FinancialTransactionStatus::Cancelled->value,
                'reason' => $reason,
            ]);

            return $locked->refresh()->load('movements.account');
        });
    }

    public function reverse(FinancialTransaction $financialTransaction, string $reason, User $actor, string $permissionModule = 'finance.transactions'): FinancialTransaction
    {
        return $this->execute('reverse', $financialTransaction, function () use ($financialTransaction, $reason, $actor, $permissionModule): FinancialTransaction {
            $original = FinancialTransaction::query()->lockForUpdate()->findOrFail($financialTransaction->id);
            $original->load(['movements' => fn ($query) => $query->lockForUpdate(), 'movements.account']);

            if ($original->status !== FinancialTransactionStatus::Settled || $original->type === FinancialTransactionType::Reversal) {
                throw ValidationException::withMessages(['financial_transaction' => 'Somente um lançamento liquidado pode ser estornado.']);
            }
            if ($original->reversed_at !== null || FinancialTransaction::query()->where('reversal_of_transaction_id', $original->id)->exists()) {
                throw ValidationException::withMessages(['financial_transaction' => 'Este lançamento já foi estornado.']);
            }

            $this->assertMovementStructure($original);
            foreach ($original->movements as $movement) {
                $this->assertAccountCanBeUsed($actor, $movement->account, 'financial_transaction', requireActive: false, permissionModule: $permissionModule);
            }

            $today = today()->toDateString();
            $reversal = FinancialTransaction::query()->create([
                'type' => FinancialTransactionType::Reversal,
                'origin' => FinancialTransactionOrigin::System,
                'category_id' => $original->category_id,
                'department_id' => $original->department_id,
                'member_id' => $original->member_id,
                'responsible_member_id' => $original->responsible_member_id,
                'title' => Str::limit('Estorno: '.$original->title, 255, ''),
                'description' => 'Estorno integral da transação '.$original->id.'.',
                'counterparty_name' => $original->counterparty_name,
                'document_number' => $original->document_number,
                'amount' => $original->amount,
                'occurred_on' => $today,
                'competence_month' => $original->competence_month,
                'payment_method' => $original->payment_method,
                'status' => FinancialTransactionStatus::Settled,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
                'reversal_of_transaction_id' => $original->id,
            ]);

            foreach ($original->movements as $movement) {
                FinancialMovement::query()->create([
                    'financial_transaction_id' => $reversal->id,
                    'financial_account_id' => $movement->financial_account_id,
                    'direction' => $movement->direction->opposite(),
                    'amount' => $movement->amount,
                    'settled_on' => $today,
                ]);
            }

            $original->update([
                'reversed_at' => now(),
                'reversed_by_user_id' => $actor->id,
                'reversal_reason' => $reason,
            ]);
            $reversal->load('movements.account');
            $this->assertMovementStructure($reversal);
            $this->recordAudit($original->origin === FinancialTransactionOrigin::Tithe ? 'financial_tithe.reversed' : 'financial_transaction.reversed', $original, $original->movements->first()->account, [
                'reason' => $reason,
                'reversal_transaction_id' => $reversal->id,
                'accounts' => $original->movements->pluck('financial_account_id')->all(),
            ]);

            return $reversal;
        });
    }

    /** @param array<string, mixed> $data */
    private function createSingleMovement(
        array $data,
        User $actor,
        FinancialTransactionType $type,
        FinancialTransactionOrigin $origin = FinancialTransactionOrigin::Manual,
        string $permissionModule = 'finance.transactions',
        ?string $memberId = null,
    ): FinancialTransaction
    {
        return $this->execute('create_'.$type->value, null, function () use ($data, $actor, $type, $origin, $permissionModule, $memberId): FinancialTransaction {
            $account = $this->lockedAccounts([(string) $data['account_id']])->first();
            if ($account === null) {
                throw ValidationException::withMessages(['account_id' => 'Selecione uma conta financeira válida.']);
            }

            $this->assertAccountCanBeUsed($actor, $account, 'account_id', permissionModule: $permissionModule);
            $category = $this->validatedCategory((string) $data['category_id'], $account, $type);
            $this->validatedDepartment($data['department_id'] ?? null, $account);
            $member = $this->validatedResponsibleMember(
                $memberId ?? ($data['responsible_member_id'] ?? null),
                $account,
                (string) $data['occurred_on'],
            );

            $transaction = $this->createTransaction($data, $actor, $type, $category, $origin, $member?->id);
            $settledOn = $transaction->status === FinancialTransactionStatus::Settled
                ? $transaction->occurred_on->toDateString()
                : null;
            $direction = $type === FinancialTransactionType::Income
                ? FinancialMovementDirection::Inflow
                : FinancialMovementDirection::Outflow;
            $this->createMovement($transaction, $account, $direction, $settledOn);
            $this->assertMovementStructure($transaction->load('movements'));
            $this->recordAudit($origin === FinancialTransactionOrigin::Tithe ? 'financial_tithe.created' : 'financial_transaction.created', $transaction, $account);

            return $transaction->load('movements.account');
        });
    }

    /** @param array<string, mixed> $data */
    private function createTransaction(
        array $data,
        User $actor,
        FinancialTransactionType $type,
        ?FinancialCategory $category,
        FinancialTransactionOrigin $origin = FinancialTransactionOrigin::Manual,
        ?string $memberId = null,
    ): FinancialTransaction {
        return FinancialTransaction::query()->create([
            'type' => $type,
            'origin' => $origin,
            'category_id' => $category?->id,
            'department_id' => $type === FinancialTransactionType::Transfer ? null : ($data['department_id'] ?? null),
            'member_id' => $memberId,
            'responsible_member_id' => $type === FinancialTransactionType::Transfer ? null : ($memberId ?? ($data['responsible_member_id'] ?? null)),
            ...$this->transactionAttributes($data, $actor),
        ]);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function transactionAttributes(array $data, User $actor, bool $forUpdate = false): array
    {
        $attributes = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'counterparty_name' => $data['counterparty_name'] ?? null,
            'document_number' => $data['document_number'] ?? null,
            'amount' => $data['amount'],
            'occurred_on' => $data['occurred_on'],
            'competence_month' => $data['competence_month'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'updated_by_user_id' => $forUpdate ? $actor->id : null,
        ];

        if (! $forUpdate) {
            $attributes['status'] = $data['status'];
            $attributes['created_by_user_id'] = $actor->id;
        }

        return $attributes;
    }

    /** @param array<string, mixed> $data */
    private function updateSingleMovement(FinancialTransaction $transaction, array $data, User $actor): void
    {
        $account = $this->lockedAccounts([(string) $data['account_id']])->first();
        if ($account === null) {
            throw ValidationException::withMessages(['account_id' => 'Selecione uma conta financeira válida.']);
        }

        $this->assertAccountCanBeUsed($actor, $account, 'account_id');
        $category = $this->validatedCategory((string) $data['category_id'], $account, $transaction->type);
        $this->validatedDepartment($data['department_id'] ?? null, $account);
        $this->validatedResponsibleMember($data['responsible_member_id'] ?? null, $account, (string) $data['occurred_on']);
        $movement = FinancialMovement::query()
            ->where('financial_transaction_id', $transaction->id)
            ->lockForUpdate()
            ->sole();
        $movement->update([
            'financial_account_id' => $account->id,
            'direction' => $transaction->type === FinancialTransactionType::Income
                ? FinancialMovementDirection::Inflow
                : FinancialMovementDirection::Outflow,
            'amount' => $data['amount'],
        ]);
        $transaction->category_id = $category->id;
        $transaction->department_id = $data['department_id'] ?? null;
        $transaction->responsible_member_id = $data['responsible_member_id'] ?? null;
        $transaction->save();
    }

    /** @param array<string, mixed> $data */
    private function updateTransfer(FinancialTransaction $transaction, array $data, User $actor): void
    {
        $accounts = $this->lockedAccounts([(string) $data['source_account_id'], (string) $data['destination_account_id']]);
        $source = $accounts->firstWhere('id', (string) $data['source_account_id']);
        $destination = $accounts->firstWhere('id', (string) $data['destination_account_id']);
        if ($source === null || $destination === null || $source->id === $destination->id) {
            throw ValidationException::withMessages(['destination_account_id' => 'Selecione duas contas financeiras diferentes.']);
        }
        $this->assertAccountCanBeUsed($actor, $source, 'source_account_id');
        $this->assertAccountCanBeUsed($actor, $destination, 'destination_account_id');

        $movements = FinancialMovement::query()
            ->where('financial_transaction_id', $transaction->id)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (FinancialMovement $movement): string => $movement->direction->value);
        if ($movements->count() !== 2 || ! $movements->has(FinancialMovementDirection::Inflow->value, FinancialMovementDirection::Outflow->value)) {
            throw ValidationException::withMessages(['financial_transaction' => 'A transferência possui uma estrutura de movimentos inválida.']);
        }
        $movements[FinancialMovementDirection::Outflow->value]->update([
            'financial_account_id' => $source->id,
            'amount' => $data['amount'],
        ]);
        $movements[FinancialMovementDirection::Inflow->value]->update([
            'financial_account_id' => $destination->id,
            'amount' => $data['amount'],
        ]);
    }

    private function revalidateForSettlement(FinancialTransaction $transaction, User $actor): void
    {
        $this->assertMovementStructure($transaction);
        foreach ($transaction->movements as $movement) {
            $this->assertAccountCanBeUsed($actor, $movement->account, 'financial_transaction');
        }

        if (in_array($transaction->type, [FinancialTransactionType::Income, FinancialTransactionType::Expense], true)) {
            $account = $transaction->movements->first()->account;
            $this->validatedCategory((string) $transaction->category_id, $account, $transaction->type);
            $this->validatedDepartment($transaction->department_id, $account);
            $this->validatedResponsibleMember(
                $transaction->responsible_member_id,
                $account,
                $transaction->occurred_on->toDateString(),
            );
        }
    }

    private function validatedCategory(string $categoryId, FinancialAccount $account, FinancialTransactionType $type): FinancialCategory
    {
        $category = FinancialCategory::query()->whereKey($categoryId)->lockForUpdate()->first();
        $expectedType = $type === FinancialTransactionType::Income ? 'income' : 'expense';

        if ($category === null || $category->status !== Status::Active || $category->type->value !== $expectedType) {
            throw ValidationException::withMessages(['category_id' => 'Selecione uma categoria ativa compatível com o tipo do lançamento.']);
        }
        if ($category->area_id !== $this->areaIdForAccount($account)) {
            throw ValidationException::withMessages(['category_id' => 'A categoria deve pertencer à mesma área da conta.']);
        }

        return $category;
    }

    private function validatedDepartment(mixed $departmentId, FinancialAccount $account): ?Department
    {
        if (! filled($departmentId)) {
            return null;
        }

        $department = Department::query()->whereKey((string) $departmentId)->lockForUpdate()->first();
        if ($department === null || $department->status !== Status::Active || $department->area_id !== $this->areaIdForAccount($account)) {
            throw ValidationException::withMessages(['department_id' => 'Selecione um departamento ativo da mesma área da conta.']);
        }

        return $department;
    }

    private function validatedResponsibleMember(mixed $memberId, FinancialAccount $account, string $occurredOn): ?Member
    {
        if (! filled($memberId)) {
            return null;
        }

        $member = Member::query()->whereKey((string) $memberId)->where('status', Status::Active->value)->lockForUpdate()->first();
        if ($member === null) {
            throw ValidationException::withMessages(['responsible_member_id' => 'Selecione um membro ativo como responsável.']);
        }

        $hasValidMembership = $member->memberships()
            ->effectiveOn($occurredOn)
            ->when(
                $account->church_id !== null,
                fn (Builder $query): Builder => $query->where('church_id', $account->church_id),
                fn (Builder $query): Builder => $query->whereHas('church', fn (Builder $query): Builder => $query
                    ->where('area_id', $this->areaIdForAccount($account))),
            )
            ->exists();
        if (! $hasValidMembership) {
            throw ValidationException::withMessages(['responsible_member_id' => 'O responsável precisa possuir vínculo válido no escopo da conta na data informada.']);
        }

        return $member;
    }

    private function assertAccountCanBeUsed(
        User $actor,
        FinancialAccount $account,
        string $field,
        bool $requireActive = true,
        string $permissionModule = 'finance.transactions',
    ): void {
        if ($requireActive && $account->status !== Status::Active) {
            throw ValidationException::withMessages([$field => 'A conta financeira selecionada precisa estar ativa.']);
        }
        if (! $this->permissions->canUseFinancialAccount(
            $actor,
            $account,
            $permissionModule,
            PermissionLevel::Write,
        )) {
            throw ValidationException::withMessages([$field => 'Você não possui permissão de escrita no escopo desta conta financeira.']);
        }
    }

    private function createMovement(
        FinancialTransaction $transaction,
        FinancialAccount $account,
        FinancialMovementDirection $direction,
        ?string $settledOn,
    ): FinancialMovement {
        return FinancialMovement::query()->create([
            'financial_transaction_id' => $transaction->id,
            'financial_account_id' => $account->id,
            'direction' => $direction,
            'amount' => $transaction->amount,
            'settled_on' => $settledOn,
        ]);
    }

    private function assertMovementStructure(FinancialTransaction $transaction): void
    {
        $transaction->loadMissing('movements');
        $movements = $transaction->movements;

        $valid = match ($transaction->type) {
            FinancialTransactionType::Income => $movements->count() === 1
                && $movements->first()->direction === FinancialMovementDirection::Inflow,
            FinancialTransactionType::Expense => $movements->count() === 1
                && $movements->first()->direction === FinancialMovementDirection::Outflow,
            FinancialTransactionType::Transfer => $movements->count() === 2
                && $movements->pluck('financial_account_id')->unique()->count() === 2
                && $movements->where('direction', FinancialMovementDirection::Inflow)->count() === 1
                && $movements->where('direction', FinancialMovementDirection::Outflow)->count() === 1,
            FinancialTransactionType::Reversal => $movements->count() >= 1,
        };

        if (! $valid || $movements->contains(fn (FinancialMovement $movement): bool => $movement->amount !== $transaction->amount)) {
            throw ValidationException::withMessages(['financial_transaction' => 'A estrutura financeira do lançamento é inválida.']);
        }
    }

    /** @param array<int, string> $ids
     * @return \Illuminate\Database\Eloquent\Collection<int, FinancialAccount>
     */
    private function lockedAccounts(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return FinancialAccount::query()
            ->with('church:id,area_id,name,status')
            ->whereIn('id', array_values(array_unique($ids)))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function areaIdForAccount(FinancialAccount $account): string
    {
        $areaId = $account->area_id ?? $account->church?->area_id;
        if ($areaId === null) {
            throw ValidationException::withMessages(['account_id' => 'Não foi possível determinar a área da conta financeira.']);
        }

        return $areaId;
    }

    /** @return array{type: string, id: string} */
    private function scopeForAccount(FinancialAccount $account): array
    {
        return $account->area_id !== null
            ? ['type' => 'area', 'id' => $account->area_id]
            : ['type' => 'church', 'id' => (string) $account->church_id];
    }

    /** @param array<string, mixed> $extra */
    private function recordAudit(
        string $action,
        FinancialTransaction $transaction,
        FinancialAccount $primaryAccount,
        array $extra = [],
    ): void {
        $scope = $this->scopeForAccount($primaryAccount);
        $this->audit->record($action, 'financial_transactions', $transaction, $scope['type'], $scope['id'], [
            'type' => $transaction->type->value,
            'origin' => $transaction->origin->value,
            'status' => $transaction->status->value,
            'amount' => $transaction->amount,
            'accounts' => $transaction->movements->pluck('financial_account_id')->all(),
            ...$extra,
        ]);
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(FinancialTransaction $transaction): array
    {
        return [
            ...$transaction->only([
                'type', 'category_id', 'department_id', 'responsible_member_id', 'title', 'description',
                'counterparty_name', 'document_number', 'amount', 'occurred_on', 'competence_month',
                'payment_method', 'status',
            ]),
            'movements' => $transaction->movements->map->only([
                'financial_account_id', 'direction', 'amount', 'settled_on',
            ])->all(),
        ];
    }

    private function execute(string $operation, ?FinancialTransaction $transaction, callable $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (ValidationException $exception) {
            $this->audit->record(
                'financial_transaction.blocked',
                'financial_transactions',
                $transaction,
                details: [
                    'operation' => $operation,
                    'validation_fields' => array_keys($exception->errors()),
                ],
            );

            throw $exception;
        }
    }
}
