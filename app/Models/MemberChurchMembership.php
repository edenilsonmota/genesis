<?php

namespace App\Models;

use App\Status;
use Database\Factories\MemberChurchMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'church_id', 'status', 'is_primary', 'joined_at', 'ended_at'])]
class MemberChurchMembership extends Model
{
    /** @use HasFactory<MemberChurchMembershipFactory> */
    use HasFactory, HasUuids;

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query
            ->where('status', Status::Active->value)
            ->whereNull('ended_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'is_primary' => 'boolean',
            'joined_at' => 'date',
            'ended_at' => 'date',
        ];
    }
}
