<?php

namespace App\Models;

use App\Enums\Sex;
use App\Status;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'cpf',
    'email',
    'phone',
    'birth_date',
    'sex',
    'postal_code',
    'street',
    'number',
    'complement',
    'neighborhood',
    'city_id',
    'status',
])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasUuids;

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(MemberChurchMembership::class);
    }

    public function churches(): BelongsToMany
    {
        return $this->belongsToMany(Church::class, 'member_church_memberships')
            ->withPivot(['id', 'status', 'is_primary', 'joined_at', 'ended_at'])
            ->withTimestamps();
    }

    public function primaryMembership(): HasOne
    {
        return $this->hasOne(MemberChurchMembership::class)
            ->where('status', Status::Active->value)
            ->whereNull('ended_at')
            ->where('is_primary', true);
    }

    #[Scope]
    protected function search(Builder $query, string $search): Builder
    {
        $digits = preg_replace('/\D/', '', $search) ?? '';

        return $query->where(function (Builder $query) use ($search, $digits): void {
            $query
                ->where('name', 'ILIKE', "%{$search}%")
                ->orWhere('email', 'ILIKE', "%{$search}%");

            if ($digits !== '') {
                $query
                    ->orWhere('cpf', 'LIKE', "%{$digits}%")
                    ->orWhere('phone', 'LIKE', "%{$digits}%");
            }
        });
    }

    #[Scope]
    protected function forChurch(Builder $query, string $churchId): Builder
    {
        return $query->whereHas('memberships', fn (Builder $query): Builder => $query
            ->active()
            ->where('church_id', $churchId));
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => Str::squish((string) $value),
        );
    }

    protected function cpf(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => preg_replace('/\D/', '', (string) $value) ?? '',
        );
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: function (mixed $value): ?string {
                $email = Str::lower(trim((string) $value));

                return $email !== '' ? $email : null;
            },
        );
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: function (mixed $value): ?string {
                $phone = preg_replace('/\D/', '', (string) $value) ?? '';

                return $phone !== '' ? $phone : null;
            },
        );
    }

    protected function postalCode(): Attribute
    {
        return Attribute::make(
            set: function (mixed $value): ?string {
                $postalCode = preg_replace('/\D/', '', (string) $value) ?? '';

                return $postalCode !== '' ? $postalCode : null;
            },
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'sex' => Sex::class,
            'status' => Status::class,
        ];
    }
}
