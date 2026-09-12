<?php

namespace App\Models;

use App\PermissionLevel;
use Database\Factories\AccessRolePermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['access_role_id', 'permission_module_id', 'level'])]
class AccessRolePermission extends Model
{
    /** @use HasFactory<AccessRolePermissionFactory> */
    use HasFactory, HasUuids;

    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(AccessRole::class);
    }

    public function permissionModule(): BelongsTo
    {
        return $this->belongsTo(PermissionModule::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => PermissionLevel::class,
        ];
    }
}
