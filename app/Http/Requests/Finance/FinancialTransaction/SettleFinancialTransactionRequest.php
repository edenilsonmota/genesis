<?php

namespace App\Http\Requests\Finance\FinancialTransaction;

use App\Models\FinancialTransaction;
use Illuminate\Foundation\Http\FormRequest;

class SettleFinancialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('financialTransaction');

        return $transaction instanceof FinancialTransaction
            && ($this->user()?->can('settle', $transaction) ?? false);
    }

    public function rules(): array
    {
        return ['settled_on' => ['required', 'date_format:Y-m-d']];
    }
}
