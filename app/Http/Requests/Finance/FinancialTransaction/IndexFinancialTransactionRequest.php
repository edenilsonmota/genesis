<?php

namespace App\Http\Requests\Finance\FinancialTransaction;

use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FinancialTransaction::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::enum(FinancialTransactionType::class)],
            'status' => ['nullable', Rule::enum(FinancialTransactionStatus::class)],
            'scope_type' => ['nullable', Rule::in(['area', 'church'])],
            'scope_id' => ['nullable', 'uuid'],
            'account_id' => ['nullable', 'uuid'],
            'category_id' => ['nullable', 'uuid'],
            'department_id' => ['nullable', 'uuid'],
            'responsible_member_id' => ['nullable', 'uuid'],
            'all_periods' => ['nullable', 'boolean'],
        ];
    }
}
