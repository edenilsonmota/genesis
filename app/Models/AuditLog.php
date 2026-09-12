<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'actor_user_id',
    'actor_name',
    'actor_username',
    'action',
    'resource',
    'route',
    'record_id',
    'scope_type',
    'scope_id',
    'ip_address',
    'details',
])]
class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
