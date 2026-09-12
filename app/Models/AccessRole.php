<?php

namespace App\Models;

use App\Status;
use Database\Factories\AccessRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['area_id', 'name', 'description', 'fixed', 'is_administrator', 'status'])]
class AccessRole extends Model
{
    /** @use HasFactory<AccessRoleFactory> */
    use HasFactory, HasUuids;

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(AccessRolePermission::class);
    }

    public function globalAssignments(): HasMany
    {
        return $this->hasMany(UserGlobalAccessRole::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fixed' => 'boolean',
            'is_administrator' => 'boolean',
            'status' => Status::class,
        ];
    }
}
