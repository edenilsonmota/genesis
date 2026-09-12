<?php

namespace App\Models;

use App\PermissionLevel;
use Database\Factories\PositionPermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['position_id', 'permission_module_id', 'level'])]
class PositionPermission extends Model
{
    /** @use HasFactory<PositionPermissionFactory> */
    use HasFactory, HasUuids;

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function permissionModule(): BelongsTo
    {
        return $this->belongsTo(PermissionModule::class);
    }

    protected function casts(): array
    {
        return ['level' => PermissionLevel::class];
    }
}
