<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Church;
use App\Services\Finance\DefaultFinancialAccountService;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChurchService
{
    public function __construct(
        private AuditService $audit,
        private DefaultFinancialAccountService $defaultFinancialAccounts,
    ) {}

    /**
     * @param  array{city_id: ?int, name: string, postal_code: ?string, street: ?string, neighborhood: ?string, number: ?string, complement: ?string, status: string}  $attributes
     */
    public function create(array $attributes): Church
    {
        try {
            return DB::transaction(function () use ($attributes): Church {
                $area = Area::query()->lockForUpdate()->first();

                if ($area === null) {
                    throw ValidationException::withMessages([
                        'area' => 'Cadastre a área antes de adicionar uma igreja.',
                    ]);
                }

                $church = $area->churches()->create($attributes);
                $this->defaultFinancialAccounts->ensureForChurch($church);
                $this->audit->record('church.created', 'churches', $church, 'church', $church->id, ['name' => $church->name]);

                return $church;
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateNameValidation($exception);

            throw $exception;
        }
    }

    /**
     * @param  array{city_id: ?int, name: string, postal_code: ?string, street: ?string, neighborhood: ?string, number: ?string, complement: ?string, status: string}  $attributes
     */
    public function update(Church $church, array $attributes): Church
    {
        try {
            return DB::transaction(function () use ($church, $attributes): Church {
                $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
                $lockedChurch->update($attributes);
                $this->audit->record('church.updated', 'churches', $lockedChurch, 'church', $lockedChurch->id, ['fields' => array_keys($attributes)]);

                return $lockedChurch;
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateNameValidation($exception);

            throw $exception;
        }
    }

    public function inactivate(Church $church): Church
    {
        return DB::transaction(function () use ($church): Church {
            $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
            $lockedChurch->update(['status' => Status::Inactive]);
            $this->audit->record('church.inactivated', 'churches', $lockedChurch, 'church', $lockedChurch->id, ['status' => Status::Inactive->value]);

            return $lockedChurch;
        });
    }

    private function throwDuplicateNameValidation(QueryException $exception): void
    {
        if ($exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'churches_area_name_lower_unique')) {
            throw ValidationException::withMessages([
                'name' => 'Já existe uma igreja com este nome na área.',
            ]);
        }
    }
}
