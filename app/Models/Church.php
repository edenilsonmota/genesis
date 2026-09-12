<?php

namespace App\Models;

use App\Status;
use Database\Factories\ChurchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'area_id',
    'city_id',
    'name',
    'postal_code',
    'street',
    'neighborhood',
    'number',
    'complement',
    'status',
])]
class Church extends Model
{
    /** @use HasFactory<ChurchFactory> */
    use HasFactory, HasUuids;

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function memberMemberships(): HasMany
    {
        return $this->hasMany(MemberChurchMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_church_memberships')
            ->withPivot(['id', 'status', 'is_primary', 'joined_at', 'ended_at'])
            ->withTimestamps();
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => Str::squish((string) $value),
        );
    }

    protected function postalCode(): Attribute
    {
        return Attribute::make(
            set: fn (mixed $value): string => preg_replace('/\D/', '', (string) $value) ?? '',
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
