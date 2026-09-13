<?php

namespace App\Http\Requests\Finance\FinancialTransaction;

use App\Enums\FinancialPaymentMethod;
use App\Enums\FinancialTransactionStatus;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialTransaction::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'source_account_id' => ['required', 'uuid', Rule::exists('financial_accounts', 'id')],
            'destination_account_id' => ['required', 'uuid', 'different:source_account_id', Rule::exists('financial_accounts', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['required', Rule::enum(FinancialPaymentMethod::class)],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', Rule::enum(FinancialTransactionStatus::class)->only([
                FinancialTransactionStatus::Pending,
                FinancialTransactionStatus::Settled,
            ])],
            'account_id' => ['prohibited'],
            'category_id' => ['prohibited'],
            'department_id' => ['prohibited'],
            'member_id' => ['prohibited'],
            'responsible_member_id' => ['prohibited'],
            'competence_month' => ['prohibited'],
            'counterparty_name' => ['prohibited'],
            'document_number' => ['prohibited'],
            'origin' => ['prohibited'],
            'type' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'updated_by_user_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => Str::squish((string) $this->input('title')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
        ]);
    }
}
