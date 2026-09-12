<?php

namespace App\Http\Requests\Finance\FinancialCategory;

use App\Models\FinancialCategory;

class UpdateFinancialCategoryRequest extends StoreFinancialCategoryRequest
{
    public function authorize(): bool
    {
        $financialCategory = $this->route('financialCategory');

        return $financialCategory instanceof FinancialCategory
            && ($this->user()?->can('update', $financialCategory) ?? false);
    }
}
