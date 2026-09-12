<?php

namespace App\Models;

use App\Status;
use Database\Factories\UserGlobalAccessRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'access_role_id', 'status', 'started_at', 'ended_at'])]
class UserGlobalAccessRole extends Model
{
    /** @use HasFactory<UserGlobalAccessRoleFactory> */
    use HasFactory, HasUuids;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(AccessRole::class);
    }

    #[Scope]
    protected function effective(Builder $query): Builder
    {
        return $query
            ->where('status', Status::Active->value)
            ->where(function (Builder $query): void {
                $query->whereNull('started_at')->orWhereDate('started_at', '<=', today());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', today());
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }
}
