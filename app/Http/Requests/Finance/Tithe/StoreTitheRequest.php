<?php

namespace App\Http\Requests\Finance\Tithe;

use App\Enums\FinancialPaymentMethod;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTitheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'uuid', Rule::exists('churches', 'id')],
            'member_id' => ['required', 'uuid', Rule::exists('members', 'id')],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'competence_month' => ['required', 'date_format:Y-m-d'],
            'payment_method' => ['required', Rule::enum(FinancialPaymentMethod::class)],
            'description' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $month = trim((string) $this->input('competence_month'));
        $paidOn = trim((string) $this->input('paid_on'));
        $amount = preg_replace('/[^\d,.-]/', '', (string) $this->input('amount')) ?? '';

        $this->merge([
            'competence_month' => $month !== '' ? $month.'-01' : null,
            'paid_on' => preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $paidOn) === 1
                ? \Carbon\Carbon::createFromFormat('d/m/Y', $paidOn)->format('Y-m-d')
                : $paidOn,
            'amount' => str_contains($amount, ',')
                ? str_replace(',', '.', str_replace('.', '', $amount))
                : $amount,
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
        ]);
    }
}
