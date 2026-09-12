<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Department;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentService
{
    public function __construct(private AuditService $audit) {}

    /** @param array{name: string, description?: string|null} $data */
    public function create(array $data): Department
    {
        return $this->withUniqueNameHandling(fn (): Department => DB::transaction(function () use ($data): Department {
            $area = Area::query()->where('status', Status::Active->value)->lockForUpdate()->first();
            if ($area === null) {
                throw ValidationException::withMessages(['name' => 'Cadastre e ative a área antes de criar departamentos.']);
            }

            $department = Department::query()->create([
                'area_id' => $area->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => Status::Active,
            ]);
            $this->audit->record('department.created', 'departments', $department, 'area', $area->id, $department->only(['name', 'description', 'status']));

            return $department;
        }));
    }

    /** @param array{name: string, description?: string|null} $data */
    public function update(Department $department, array $data): Department
    {
        return $this->withUniqueNameHandling(fn (): Department => DB::transaction(function () use ($department, $data): Department {
            $locked = Department::query()->lockForUpdate()->findOrFail($department->id);
            $before = $locked->only(['name', 'description']);
            $locked->update(['name' => $data['name'], 'description' => $data['description'] ?? null]);
            $this->audit->record('department.updated', 'departments', $locked, 'area', $locked->area_id, ['before' => $before, 'after' => $locked->only(['name', 'description'])]);

            return $locked->refresh();
        }));
    }

    public function changeStatus(Department $department, Status $status): Department
    {
        return DB::transaction(function () use ($department, $status): Department {
            $locked = Department::query()->lockForUpdate()->findOrFail($department->id);
            $locked->update(['status' => $status]);
            $this->audit->record('department.status_changed', 'departments', $locked, 'area', $locked->area_id, ['status' => $status->value]);

            return $locked->refresh();
        });
    }

    /** @template T */
    private function withUniqueNameHandling(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['name' => 'Já existe um departamento com este nome na área.']);
            }

            throw $exception;
        }
    }
}
