<?php

namespace App\Services;

use App\Models\Area;
use App\Services\Finance\DefaultFinancialAccountService;
use App\Services\Finance\FinancialCategoryProvisioningService;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AreaService
{
    public function __construct(
        private AuditService $audit,
        private DefaultFinancialAccountService $defaultFinancialAccounts,
        private FinancialCategoryProvisioningService $financialCategories,
        private CalendarEventTypeService $calendarEventTypes,
    ) {}

    /**
     * @param  array{name: string, description: ?string, status: string}  $attributes
     */
    public function create(array $attributes): Area
    {
        try {
            return DB::transaction(function () use ($attributes): Area {
                DB::statement('LOCK TABLE areas IN EXCLUSIVE MODE');

                if (Area::query()->exists()) {
                    throw ValidationException::withMessages([
                        'name' => 'Esta instalação já possui uma área cadastrada.',
                    ]);
                }

                $area = Area::query()->create($attributes);
                $this->defaultFinancialAccounts->ensureForArea($area);
                $this->financialCategories->ensureForArea($area);
                $this->calendarEventTypes->ensureForArea($area);
                $this->audit->record('area.created', 'areas', $area, 'area', $area->id, $area->only(['name', 'description', 'status']));

                return $area;
            });
        } catch (QueryException $exception) {
            if ($this->isSingletonViolation($exception)) {
                throw ValidationException::withMessages([
                    'name' => 'Esta instalação já possui uma área cadastrada.',
                ]);
            }

            throw $exception;
        }
    }

    /**
     * @param  array{name: string, description: ?string, status: string}  $attributes
     */
    public function update(Area $area, array $attributes): Area
    {
        return DB::transaction(function () use ($area, $attributes): Area {
            $lockedArea = Area::query()->lockForUpdate()->findOrFail($area->getKey());

            if ($attributes['status'] === Status::Inactive->value
                && $lockedArea->churches()->where('status', Status::Active->value)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'A área não pode ser inativada enquanto possuir igrejas ativas.',
                ]);
            }

            $lockedArea->update($attributes);
            $this->audit->record('area.updated', 'areas', $lockedArea, 'area', $lockedArea->id, ['fields' => array_keys($attributes)]);

            return $lockedArea;
        });
    }

    private function isSingletonViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'areas_singleton_unique');
    }
}
