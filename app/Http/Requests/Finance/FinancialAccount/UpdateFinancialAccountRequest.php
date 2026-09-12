<?php

namespace App\Http\Requests\Finance\FinancialAccount;

use App\Models\FinancialAccount;

class UpdateFinancialAccountRequest extends StoreFinancialAccountRequest
{
    public function authorize(): bool
    {
        $financialAccount = $this->route('financialAccount');

        return $financialAccount instanceof FinancialAccount
            && ($this->user()?->can('update', $financialAccount) ?? false);
    }
}
