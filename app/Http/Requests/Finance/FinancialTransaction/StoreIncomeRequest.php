<?php

namespace App\Http\Requests\Finance\FinancialTransaction;

use App\Enums\FinancialPaymentMethod;
use App\Enums\FinancialTransactionStatus;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', FinancialTransaction::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'uuid', Rule::exists('financial_accounts', 'id')],
            'category_id' => ['required', 'uuid', Rule::exists('financial_categories', 'id')],
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')],
            'responsible_member_id' => ['nullable', 'uuid', Rule::exists('members', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
            'competence_month' => ['nullable', 'date_format:Y-m-d'],
            'payment_method' => ['nullable', 'required_unless:status,draft', Rule::enum(FinancialPaymentMethod::class)],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            'document_number' => ['prohibited'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', Rule::enum(FinancialTransactionStatus::class)->only([
                FinancialTransactionStatus::Draft,
                FinancialTransactionStatus::Pending,
                FinancialTransactionStatus::Settled,
            ])],
            'origin' => ['prohibited'],
            'type' => ['prohibited'],
            'member_id' => ['prohibited'],
            'created_by_user_id' => ['prohibited'],
            'updated_by_user_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $competence = (string) $this->input('competence_month', '');
        $this->merge([
            'title' => Str::squish((string) $this->input('title')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'counterparty_name' => filled($this->input('counterparty_name')) ? Str::squish((string) $this->input('counterparty_name')) : null,
            'department_id' => filled($this->input('department_id')) ? $this->input('department_id') : null,
            'responsible_member_id' => filled($this->input('responsible_member_id')) ? $this->input('responsible_member_id') : null,
            'competence_month' => preg_match('/^\d{4}-\d{2}$/', $competence) === 1 ? $competence.'-01' : ($competence !== '' ? $competence : null),
            'payment_method' => filled($this->input('payment_method')) ? $this->input('payment_method') : null,
        ]);
    }
}
