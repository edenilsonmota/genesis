<?php

namespace App\Models;

use App\Enums\FinancialCategoryType;
use App\Status;
use Database\Factories\FinancialCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['area_id', 'name', 'type', 'description', 'fixed', 'status'])]
class FinancialCategory extends Model
{
    /** @use HasFactory<FinancialCategoryFactory> */
    use HasFactory, HasUuids;

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', Status::Active->value);
    }

    #[Scope]
    protected function ofType(Builder $query, FinancialCategoryType|string $type): Builder
    {
        return $query->where('type', $type instanceof FinancialCategoryType ? $type->value : $type);
    }

    protected function name(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): string => Str::squish((string) $value));
    }

    protected function casts(): array
    {
        return [
            'type' => FinancialCategoryType::class,
            'fixed' => 'boolean',
            'status' => Status::class,
        ];
    }
}
