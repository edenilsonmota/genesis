<?php

namespace App\Http\Requests\Finance\Tithe;

use App\Enums\FinancialPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTitheRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'competence_month_number' => ['required', 'integer', 'between:1,12'],
            'competence_year' => ['required', 'integer', 'between:2000,2100'],
            'payment_method' => ['required', Rule::enum(FinancialPaymentMethod::class)],
            'description' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $amount = preg_replace('/[^\d,.-]/', '', (string) $this->input('amount')) ?? '';
        $this->merge([
            'amount' => str_contains($amount, ',') ? str_replace(',', '.', str_replace('.', '', $amount)) : $amount,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
        ]);
    }
}
