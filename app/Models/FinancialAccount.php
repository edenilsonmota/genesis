<?php

namespace App\Models;

use App\Enums\FinancialAccountType;
use App\Status;
use Database\Factories\FinancialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['area_id', 'church_id', 'name', 'type', 'institution', 'description', 'status'])]
class FinancialAccount extends Model
{
    /** @use HasFactory<FinancialAccountFactory> */
    use HasFactory, HasUuids;

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', Status::Active->value);
    }

    #[Scope]
    protected function forArea(Builder $query, string $areaId): Builder
    {
        return $query->where('area_id', $areaId)->whereNull('church_id');
    }

    #[Scope]
    protected function forChurch(Builder $query, string $churchId): Builder
    {
        return $query->where('church_id', $churchId)->whereNull('area_id');
    }

    #[Scope]
    protected function ofType(Builder $query, FinancialAccountType|string $type): Builder
    {
        return $query->where('type', $type instanceof FinancialAccountType ? $type->value : $type);
    }

    protected function name(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): string => Str::squish((string) $value));
    }

    protected function institution(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): ?string => filled($value) ? Str::squish((string) $value) : null);
    }

    protected function casts(): array
    {
        return [
            'type' => FinancialAccountType::class,
            'status' => Status::class,
        ];
    }
}
