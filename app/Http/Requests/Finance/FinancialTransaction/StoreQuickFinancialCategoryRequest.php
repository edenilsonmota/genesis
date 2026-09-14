<?php

namespace App\Http\Requests\Finance\FinancialTransaction;

use App\Enums\FinancialCategoryType;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreQuickFinancialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialTransaction::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(FinancialCategoryType::class)],
            'area_id' => ['required', 'uuid', Rule::exists('areas', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => Str::squish((string) $this->input('name'))]);
    }
}
