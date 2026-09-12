<?php

namespace App\Models;

use App\Status;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'status' => Status::class,
        ];
    }
}
