<?php

namespace App\Models;

use App\Enums\FinancialMovementDirection;
use Database\Factories\FinancialMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['financial_transaction_id', 'financial_account_id', 'direction', 'amount', 'settled_on'])]
class FinancialMovement extends Model
{
    /** @use HasFactory<FinancialMovementFactory> */
    use HasFactory, HasUuids;

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    protected function casts(): array
    {
        return [
            'direction' => FinancialMovementDirection::class,
            'amount' => 'decimal:2',
            'settled_on' => 'date',
        ];
    }
}
