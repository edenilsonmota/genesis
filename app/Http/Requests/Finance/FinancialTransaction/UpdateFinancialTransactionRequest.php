<?php

namespace App\Http\Requests\Finance\FinancialTransaction;

use App\Enums\FinancialPaymentMethod;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('financialTransaction');

        return $transaction instanceof FinancialTransaction
            && ($this->user()?->can('update', $transaction) ?? false);
    }

    public function rules(): array
    {
        $transaction = $this->route('financialTransaction');
        $isTransfer = $transaction instanceof FinancialTransaction
            && $transaction->type === FinancialTransactionType::Transfer;

        return [
            'account_id' => [$isTransfer ? 'prohibited' : 'required', 'uuid', Rule::exists('financial_accounts', 'id')],
            'source_account_id' => [$isTransfer ? 'required' : 'prohibited', 'uuid', Rule::exists('financial_accounts', 'id')],
            'destination_account_id' => [$isTransfer ? 'required' : 'prohibited', 'uuid', 'different:source_account_id', Rule::exists('financial_accounts', 'id')],
            'category_id' => [$isTransfer ? 'prohibited' : 'required', 'uuid', Rule::exists('financial_categories', 'id')],
            'department_id' => [$isTransfer ? 'prohibited' : 'nullable', 'nullable', 'uuid', Rule::exists('departments', 'id')],
            'responsible_member_id' => [$isTransfer ? 'prohibited' : 'nullable', 'nullable', 'uuid', Rule::exists('members', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'occurred_on' => ['required', 'date_format:Y-m-d'],
            'competence_month' => [$isTransfer ? 'prohibited' : 'nullable', 'nullable', 'date_format:Y-m-d'],
            'payment_method' => ['required', Rule::enum(FinancialPaymentMethod::class)],
            'counterparty_name' => [$isTransfer ? 'prohibited' : 'nullable', 'nullable', 'string', 'max:255'],
            'document_number' => [! $isTransfer && $transaction?->type === FinancialTransactionType::Expense ? 'nullable' : 'prohibited', 'nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['prohibited'],
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
        $competenceNumber = (int) $this->input('competence_month_number', 0);
        $competenceYear = (int) $this->input('competence_year', 0);
        $this->merge([
            'title' => Str::squish((string) $this->input('title')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'counterparty_name' => filled($this->input('counterparty_name')) ? Str::squish((string) $this->input('counterparty_name')) : null,
            'document_number' => filled($this->input('document_number')) ? Str::squish((string) $this->input('document_number')) : null,
            'department_id' => filled($this->input('department_id')) ? $this->input('department_id') : null,
            'responsible_member_id' => filled($this->input('responsible_member_id')) ? $this->input('responsible_member_id') : null,
            'competence_month' => $competenceNumber >= 1 && $competenceNumber <= 12 && $competenceYear >= 2000 && $competenceYear <= 2100
                ? sprintf('%d-%02d-01', $competenceYear, $competenceNumber)
                : (preg_match('/^\d{4}-\d{2}$/', $competence) === 1 ? $competence.'-01' : ($competence !== '' ? $competence : null)),
        ]);
    }
}
