<?php

namespace App\Services\Finance;

use App\Models\Area;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\User;
use App\PermissionLevel;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialAccountService
{
    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): FinancialAccount
    {
        return $this->withUniqueNameHandling(fn (): FinancialAccount => DB::transaction(function () use ($data, $actor): FinancialAccount {
            [$areaId, $churchId, $scopeType, $scopeId] = $this->validatedOwner($data, $actor);
            $account = FinancialAccount::query()->create([
                'area_id' => $areaId,
                'church_id' => $churchId,
                'name' => $data['name'],
                'type' => $data['type'],
                'institution' => $data['institution'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);

            $this->audit->record(
                'financial_account.created',
                'financial_accounts',
                $account,
                $scopeType,
                $scopeId,
                $account->only(['area_id', 'church_id', 'name', 'type', 'institution', 'status']),
            );

            return $account;
        }));
    }

    /** @param array<string, mixed> $data */
    public function update(FinancialAccount $financialAccount, array $data, User $actor): FinancialAccount
    {
        return $this->withUniqueNameHandling(fn (): FinancialAccount => DB::transaction(function () use ($financialAccount, $data, $actor): FinancialAccount {
            $locked = FinancialAccount::query()->lockForUpdate()->findOrFail($financialAccount->id);
            [$areaId, $churchId, $scopeType, $scopeId] = $this->validatedOwner($data, $actor);
            $before = $locked->only(['area_id', 'church_id', 'name', 'type', 'institution', 'description', 'status']);

            $locked->update([
                'area_id' => $areaId,
                'church_id' => $churchId,
                'name' => $data['name'],
                'type' => $data['type'],
                'institution' => $data['institution'] ?? null,
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);

            $this->audit->record('financial_account.updated', 'financial_accounts', $locked, $scopeType, $scopeId, [
                'before' => $before,
                'after' => $locked->only(['area_id', 'church_id', 'name', 'type', 'institution', 'description', 'status']),
            ]);

            return $locked->refresh();
        }));
    }

    public function changeStatus(FinancialAccount $financialAccount, Status $status): FinancialAccount
    {
        return DB::transaction(function () use ($financialAccount, $status): FinancialAccount {
            $locked = FinancialAccount::query()->lockForUpdate()->findOrFail($financialAccount->id);
            $before = $locked->status;
            $locked->update(['status' => $status]);

            $scopeType = $locked->area_id !== null ? 'area' : 'church';
            $scopeId = $locked->area_id ?? $locked->church_id;
            $this->audit->record('financial_account.status_changed', 'financial_accounts', $locked, $scopeType, $scopeId, [
                'before' => $before->value,
                'after' => $status->value,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?string, 1: ?string, 2: string, 3: string}
     */
    private function validatedOwner(array $data, User $actor): array
    {
        $ownerType = $data['owner_type'] ?? null;
        $areaId = filled($data['area_id'] ?? null) ? (string) $data['area_id'] : null;
        $churchId = filled($data['church_id'] ?? null) ? (string) $data['church_id'] : null;

        if ($ownerType === 'area' && $areaId !== null && $churchId === null) {
            if (! $actor->isGlobalAdministrator()) {
                throw ValidationException::withMessages(['owner_type' => 'Somente o administrador global pode administrar contas da área.']);
            }

            $area = Area::query()->whereKey($areaId)->where('status', Status::Active->value)->lockForUpdate()->first();
            if ($area === null) {
                throw ValidationException::withMessages(['area_id' => 'Selecione a área ativa do sistema.']);
            }

            return [$area->id, null, 'area', $area->id];
        }

        if ($ownerType === 'church' && $areaId === null && $churchId !== null) {
            $church = Church::query()
                ->whereKey($churchId)
                ->where('status', Status::Active->value)
                ->whereHas('area', fn (Builder $query): Builder => $query->where('status', Status::Active->value))
                ->lockForUpdate()
                ->first();
            if ($church === null) {
                throw ValidationException::withMessages(['church_id' => 'Selecione uma igreja ativa da área.']);
            }

            if (! $actor->isGlobalAdministrator()) {
                $currentChurch = $this->permissions->currentChurch($actor);
                if ($currentChurch?->id !== $church->id || ! $this->permissions->can($actor, 'finance.accounts', PermissionLevel::Write, $church)) {
                    throw ValidationException::withMessages(['church_id' => 'A conta deve pertencer à igreja ativa do usuário.']);
                }
            }

            return [null, $church->id, 'church', $church->id];
        }

        throw ValidationException::withMessages(['owner_type' => 'A conta deve pertencer exclusivamente à área ou a uma igreja.']);
    }

    private function withUniqueNameHandling(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['name' => 'Já existe uma conta financeira com este nome para o proprietário selecionado.']);
            }

            throw $exception;
        }
    }
}
