<?php

namespace App\Models;

use App\Status;
use Database\Factories\MemberPositionAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_church_membership_id', 'position_id', 'status', 'started_at', 'ended_at'])]
class MemberPositionAssignment extends Model
{
    /** @use HasFactory<MemberPositionAssignmentFactory> */
    use HasFactory, HasUuids;

    public function membership(): BelongsTo
    {
        return $this->belongsTo(MemberChurchMembership::class, 'member_church_membership_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    #[Scope]
    protected function effectiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where('member_position_assignments.status', Status::Active->value)
            ->whereDate('member_position_assignments.started_at', '<=', $date)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('member_position_assignments.ended_at')
                ->orWhereDate('member_position_assignments.ended_at', '>=', $date));
    }

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'started_at' => 'date',
            'ended_at' => 'date',
        ];
    }
}
