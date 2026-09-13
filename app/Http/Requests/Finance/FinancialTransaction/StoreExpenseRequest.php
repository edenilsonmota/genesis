<?php

namespace App\Http\Requests\Finance\FinancialTransaction;

class StoreExpenseRequest extends StoreIncomeRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'document_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge([
            'document_number' => filled($this->input('document_number'))
                ? str($this->input('document_number'))->squish()->toString()
                : null,
        ]);
    }
}
