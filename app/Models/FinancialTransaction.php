<?php

namespace App\Models;

use App\Enums\FinancialPaymentMethod;
use App\Enums\FinancialTransactionOrigin;
use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use Database\Factories\FinancialTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'type', 'origin', 'category_id', 'department_id', 'member_id', 'responsible_member_id',
    'title', 'description', 'counterparty_name', 'document_number', 'amount', 'occurred_on',
    'competence_month', 'payment_method', 'status', 'created_by_user_id', 'updated_by_user_id',
    'cancelled_by_user_id', 'cancelled_at', 'cancellation_reason', 'reversed_at',
    'reversed_by_user_id', 'reversal_reason', 'reversal_of_transaction_id',
])]
class FinancialTransaction extends Model
{
    /** @use HasFactory<FinancialTransactionFactory> */
    use HasFactory, HasUuids;

    public function movements(): HasMany
    {
        return $this->hasMany(FinancialMovement::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function responsibleMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'responsible_member_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function reversedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    public function reversalOfTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_transaction_id');
    }

    public function reversalTransaction(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_transaction_id');
    }

    #[Scope]
    protected function settled(Builder $query): Builder
    {
        return $query->where('status', FinancialTransactionStatus::Settled->value);
    }

    public function isEditable(): bool
    {
        return $this->origin === FinancialTransactionOrigin::Manual
            && in_array($this->status, [FinancialTransactionStatus::Draft, FinancialTransactionStatus::Pending], true);
    }

    protected function title(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): string => Str::squish((string) $value));
    }

    protected function counterpartyName(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): ?string => filled($value) ? Str::squish((string) $value) : null);
    }

    protected function documentNumber(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): ?string => filled($value) ? Str::squish((string) $value) : null);
    }

    protected function casts(): array
    {
        return [
            'type' => FinancialTransactionType::class,
            'origin' => FinancialTransactionOrigin::class,
            'payment_method' => FinancialPaymentMethod::class,
            'status' => FinancialTransactionStatus::class,
            'amount' => 'decimal:2',
            'occurred_on' => 'date',
            'competence_month' => 'date',
            'cancelled_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }
}
