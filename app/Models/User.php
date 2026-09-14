<?php

namespace App\Models;

use App\Status;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'member_id',
    'display_name',
    'cpf',
    'email',
    'phone',
    'profile_photo_path',
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

    public function isActive(): bool
    {
        return $this->status === Status::Active;
    }

    public function isGlobalAdministrator(): bool
    {
        return $this->isActive() && $this->is_global_administrator;
    }

    protected function username(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => self::normalizeUsername((string) $value),
        );
    }

    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => ($cpf = preg_replace('/\D/', '', (string) $value) ?? '') !== '' ? $cpf : null,
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => ($email = Str::lower(trim((string) $value))) !== '' ? $email : null,
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): ?string => ($phone = preg_replace('/\D/', '', (string) $value) ?? '') !== '' ? $phone : null,
        );
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => Status::class,
            'is_global_administrator' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
