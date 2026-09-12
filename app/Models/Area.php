<?php

namespace App\Models;

use App\Status;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'status'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory, HasUuids;

    public function accessRoles(): HasMany
    {
        return $this->hasMany(AccessRole::class);
    }

    public function churches(): HasMany
    {
        return $this->hasMany(Church::class);
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => Str::squish((string) $value),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }
}
