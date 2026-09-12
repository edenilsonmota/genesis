<?php

namespace App\Services\Finance;

use App\Models\Area;
use App\Models\FinancialCategory;
use App\Services\AuditService;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinancialCategoryService
{
    public function __construct(private AuditService $audit) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): FinancialCategory
    {
        return $this->withUniqueNameHandling(fn (): FinancialCategory => DB::transaction(function () use ($data): FinancialCategory {
            $area = $this->activeArea((string) $data['area_id']);
            $category = FinancialCategory::query()->create([
                'area_id' => $area->id,
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'fixed' => false,
                'status' => $data['status'],
            ]);

            $this->audit->record('financial_category.created', 'financial_categories', $category, 'area', $area->id, $category->only(['name', 'type', 'description', 'status']));

            return $category;
        }));
    }

    /** @param array<string, mixed> $data */
    public function update(FinancialCategory $financialCategory, array $data): FinancialCategory
    {
        return $this->withUniqueNameHandling(fn (): FinancialCategory => DB::transaction(function () use ($financialCategory, $data): FinancialCategory {
            $locked = FinancialCategory::query()->lockForUpdate()->findOrFail($financialCategory->id);
            $this->assertMutable($locked);
            $area = $this->activeArea((string) $data['area_id']);
            if ($area->id !== $locked->area_id) {
                throw ValidationException::withMessages(['area_id' => 'A categoria deve permanecer na área em que foi criada.']);
            }

            $before = $locked->only(['name', 'type', 'description', 'status']);
            $locked->update([
                'name' => $data['name'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
            ]);
            $this->audit->record('financial_category.updated', 'financial_categories', $locked, 'area', $locked->area_id, [
                'before' => $before,
                'after' => $locked->only(['name', 'type', 'description', 'status']),
            ]);

            return $locked->refresh();
        }));
    }

    public function changeStatus(FinancialCategory $financialCategory, Status $status): FinancialCategory
    {
        return DB::transaction(function () use ($financialCategory, $status): FinancialCategory {
            $locked = FinancialCategory::query()->lockForUpdate()->findOrFail($financialCategory->id);
            $this->assertMutable($locked);
            $before = $locked->status;
            $locked->update(['status' => $status]);
            $this->audit->record('financial_category.status_changed', 'financial_categories', $locked, 'area', $locked->area_id, [
                'before' => $before->value,
                'after' => $status->value,
            ]);

            return $locked->refresh();
        });
    }

    private function activeArea(string $areaId): Area
    {
        $area = Area::query()->whereKey($areaId)->where('status', Status::Active->value)->lockForUpdate()->first();
        if ($area === null || Area::query()->where('status', Status::Active->value)->whereKeyNot($areaId)->exists()) {
            throw ValidationException::withMessages(['area_id' => 'Selecione a área ativa do sistema.']);
        }

        return $area;
    }

    private function assertMutable(FinancialCategory $financialCategory): void
    {
        if ($financialCategory->fixed) {
            throw ValidationException::withMessages(['financial_category' => 'A estrutura de uma categoria financeira fixa é protegida.']);
        }
    }

    private function withUniqueNameHandling(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['name' => 'Já existe uma categoria financeira com este nome e tipo na área.']);
            }

            throw $exception;
        }
    }
}
