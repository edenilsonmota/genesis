<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Church;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChurchService
{
    /**
     * @param  array{city_id: int, name: string, postal_code: string, street: string, neighborhood: string, number: string, complement: ?string, status: string}  $attributes
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

                return $area->churches()->create($attributes);
            });
        } catch (QueryException $exception) {
            $this->throwDuplicateNameValidation($exception);

            throw $exception;
        }
    }

    /**
     * @param  array{city_id: int, name: string, postal_code: string, street: string, neighborhood: string, number: string, complement: ?string, status: string}  $attributes
     */
    public function update(Church $church, array $attributes): Church
    {
        try {
            return DB::transaction(function () use ($church, $attributes): Church {
                $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
                $lockedChurch->update($attributes);

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
