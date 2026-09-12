<?php

namespace App\Models;

use App\Status;
use Database\Factories\PermissionModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'description', 'category', 'status'])]
class PermissionModule extends Model
{
    /** @use HasFactory<PermissionModuleFactory> */
    use HasFactory, HasUuids;

    public function positionPermissions(): HasMany
    {
        return $this->hasMany(PositionPermission::class);
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
