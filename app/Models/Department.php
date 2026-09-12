<?php

namespace App\Models;

use App\Status;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['area_id', 'name', 'description', 'status'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, HasUuids;

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', Status::Active->value);
    }

    protected function name(): Attribute
    {
        return Attribute::make(set: fn (mixed $value): string => Str::upper(Str::squish((string) $value)));
    }

    protected function casts(): array
    {
        return ['status' => Status::class];
    }
}
