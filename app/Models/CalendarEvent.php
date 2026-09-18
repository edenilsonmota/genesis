<?php

namespace App\Models;

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventVisibility;
use Database\Factories\CalendarEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'area_id',
    'church_id',
    'department_id',
    'responsible_member_id',
    'created_by_user_id',
    'title',
    'calendar_event_type_id',
    'starts_at',
    'ends_at',
    'all_day',
    'location',
    'description',
    'visibility',
    'status',
    'creator_can_edit',
    'responsible_can_edit',
])]
class CalendarEvent extends Model
{
    /** @use HasFactory<CalendarEventFactory> */
    use HasFactory, HasUuids;

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function responsibleMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'responsible_member_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(CalendarEventType::class, 'calendar_event_type_id');
    }

    public function isAreaWide(): bool
    {
        return $this->church_id === null;
    }

    public function isCancelled(): bool
    {
        return $this->status === CalendarEventStatus::Cancelled;
    }

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'all_day' => 'boolean',
            'visibility' => CalendarEventVisibility::class,
            'status' => CalendarEventStatus::class,
            'creator_can_edit' => 'boolean',
            'responsible_can_edit' => 'boolean',
        ];
    }
}
