<?php

namespace App\Http\Requests\Finance\FinancialCategory;

use App\Enums\FinancialCategoryType;
use App\Models\FinancialCategory;
use App\Status;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFinancialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'area_id' => [
                'required',
                'uuid',
                Rule::exists('areas', 'id')->where(fn (Builder $query): Builder => $query->where('status', Status::Active->value)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(FinancialCategoryType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(Status::class)->only([Status::Active, Status::Inactive])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['area_id', 'name', 'type'])) {
                return;
            }

            $query = FinancialCategory::query()
                ->where('area_id', $this->string('area_id')->toString())
                ->where('type', $this->string('type')->toString())
                ->whereRaw('LOWER(name) = ?', [Str::lower($this->string('name')->toString())]);

            $financialCategory = $this->route('financialCategory');
            if ($financialCategory instanceof FinancialCategory) {
                $query->whereKeyNot($financialCategory->id);
            }

            if ($query->exists()) {
                $validator->errors()->add('name', 'Já existe uma categoria financeira com este nome e tipo na área.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
        ]);
    }
}
