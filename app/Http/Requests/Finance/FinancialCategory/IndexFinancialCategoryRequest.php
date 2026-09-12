<?php

namespace App\Http\Requests\Finance\FinancialCategory;

use App\Enums\FinancialCategoryType;
use App\Models\FinancialCategory;
use App\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexFinancialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FinancialCategory::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(FinancialCategoryType::class)],
            'status' => ['nullable', Rule::enum(Status::class)->only([Status::Active, Status::Inactive])],
        ];
    }
}
