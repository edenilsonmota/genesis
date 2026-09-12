<?php

namespace App\Models;

use App\PermissionLevel;
use App\Status;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'member_id',
    'display_name',
    'username',
    'password',
    'status',
    'must_change_password',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    public static function normalizeUsername(string $username): string
    {
        return Str::of($username)->trim()->lower()->toString();
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function globalAccessRoles(): HasMany
    {
        return $this->hasMany(UserGlobalAccessRole::class);
    }

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }

    public function isGlobalAdministrator(): bool
    {
        return $this->globalAccessRoles()
            ->effective()
            ->whereHas('accessRole', function (Builder $query): void {
                $query
                    ->whereNull('area_id')
                    ->where('fixed', true)
                    ->where('is_administrator', true)
                    ->where('status', Status::Active->value);
            })
            ->exists();
    }

    public function hasGlobalPermission(string $moduleKey, PermissionLevel $requiredLevel): bool
    {
        if ($this->isGlobalAdministrator()) {
            return true;
        }

        $acceptedLevels = $requiredLevel === PermissionLevel::Write
            ? [PermissionLevel::Write->value]
            : [PermissionLevel::Read->value, PermissionLevel::Write->value];

        return $this->globalAccessRoles()
            ->effective()
            ->whereHas('accessRole', function (Builder $query) use ($moduleKey, $acceptedLevels): void {
                $query
                    ->whereNull('area_id')
                    ->where('status', Status::Active->value)
                    ->whereHas('permissions', function (Builder $query) use ($moduleKey, $acceptedLevels): void {
                        $query
                            ->whereIn('level', $acceptedLevels)
                            ->whereHas('permissionModule', fn (Builder $query): Builder => $query
                                ->where('key', $moduleKey)
                                ->where('status', Status::Active->value));
                    });
            })
            ->exists();
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => self::normalizeUsername((string) $value),
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => Status::class,
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
