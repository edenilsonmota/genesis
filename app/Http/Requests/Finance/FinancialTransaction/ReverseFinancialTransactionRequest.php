<?php

namespace App\Http\Requests\Finance\FinancialTransaction;

use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;

class ReverseFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('financialTransaction');

        return $transaction instanceof FinancialTransaction
            && ($this->user()?->can('reverse', $transaction) ?? false);
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:2000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason'))]);
    }
}
