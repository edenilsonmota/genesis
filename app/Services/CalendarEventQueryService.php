<?php

namespace App\Services;

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventVisibility;
use App\Models\CalendarEvent;
use App\Models\Church;
use App\Models\User;
use App\PermissionLevel;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class CalendarEventQueryService
{
    public function __construct(private PermissionService $permissions) {}

    /** @param array<string, mixed> $filters */
    public function visibleBetween(
        User $user,
        ?Church $church,
        CarbonInterface $start,
        CarbonInterface $end,
        array $filters = [],
    ): Builder {
        $query = CalendarEvent::query()
            ->with(['area:id,name', 'church:id,name', 'eventType:id,name,color', 'department:id,name', 'responsibleMember:id,name', 'createdByUser:id,display_name'])
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($church, fn (Builder $query): Builder => $query
                ->where('area_id', $church->area_id)
                ->where(fn (Builder $query): Builder => $query
                    ->whereNull('church_id')->orWhere('church_id', $church->id)))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('calendar_event_type_id', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['department_id'] ?? null, fn (Builder $query, string $departmentId): Builder => $query->where('department_id', $departmentId));

        if ($user->isGlobalAdministrator()) {
            return $query;
        }

        abort_unless($church !== null && $this->permissions->can($user, 'calendar', PermissionLevel::Read, $church), 403);

        $canManage = $this->permissions->can($user, 'calendar', PermissionLevel::Write, $church);
        if ($canManage) {
            return $query;
        }

        $departmentIds = $this->permissions->activeDepartmentIds($user, $church);
        $ownership = fn (Builder $query): Builder => $query
            ->where('created_by_user_id', $user->id)
            ->when($user->member_id !== null, fn (Builder $query): Builder => $query
                ->orWhere('responsible_member_id', $user->member_id));

        return $query
            ->where(fn (Builder $query): Builder => $query
                ->where('status', '!=', CalendarEventStatus::Draft->value)
                ->orWhere($ownership))
            ->where(fn (Builder $query): Builder => $query
                ->where('visibility', CalendarEventVisibility::Church->value)
                ->orWhere($ownership)
                ->when($departmentIds->isNotEmpty(), fn (Builder $query): Builder => $query
                    ->orWhere(fn (Builder $query): Builder => $query
                        ->where('visibility', CalendarEventVisibility::Department->value)
                        ->whereIn('department_id', $departmentIds))));
    }
}
