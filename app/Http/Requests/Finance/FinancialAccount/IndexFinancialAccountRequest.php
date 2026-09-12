<?php

namespace App\Http\Requests\Finance\FinancialAccount;

use App\Enums\FinancialAccountType;
use App\Models\FinancialAccount;
use App\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FinancialAccount::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'owner_type' => ['nullable', Rule::in(['area', 'church'])],
            'owner_id' => ['nullable', 'uuid'],
            'type' => ['nullable', Rule::enum(FinancialAccountType::class)],
            'status' => ['nullable', Rule::enum(Status::class)->only([Status::Active, Status::Inactive])],
        ];
    }
}
