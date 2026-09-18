<?php

namespace App\Http\Controllers;

use App\Enums\CalendarEventStatus;
use App\Http\Requests\Calendar\IndexCalendarEventFeedRequest;
use App\Models\CalendarEvent;
use App\Services\CalendarEventQueryService;
use App\Services\PermissionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CalendarEventFeedController extends Controller
{
    public function __invoke(
        IndexCalendarEventFeedRequest $request,
        PermissionService $permissions,
        CalendarEventQueryService $events,
    ): JsonResponse {
        $filters = $request->safe()->only(['type', 'status', 'department_id']);
        $church = $permissions->currentChurch($request->user());
        $timezone = config('genesis.calendar.timezone');
        $items = $events->visibleBetween(
            $request->user(),
            $church,
            CarbonImmutable::parse($request->validated('start')),
            CarbonImmutable::parse($request->validated('end')),
            $filters,
        )->orderBy('starts_at')->get();

        return response()->json($items->map(fn (CalendarEvent $event): array => $this->toFullCalendar($event, $timezone))->values());
    }

    /** @return array<string, mixed> */
    private function toFullCalendar(CalendarEvent $event, string $timezone): array
    {
        $canEdit = Gate::allows('update', $event);
        $color = match ($event->status) {
            CalendarEventStatus::Draft => '#F59E0B',
            CalendarEventStatus::Cancelled => '#EF4444',
            default => $event->eventType->color,
        };

        return [
            'id' => $event->id,
            'title' => $event->title,
            'start' => $event->all_day ? $event->starts_at->setTimezone($timezone)->toDateString() : $event->starts_at->toIso8601String(),
            'end' => $event->all_day ? $event->ends_at->setTimezone($timezone)->toDateString() : $event->ends_at->toIso8601String(),
            'allDay' => $event->all_day,
            'url' => route('calendar.events.show', $event),
            'editable' => $canEdit,
            'startEditable' => $canEdit,
            'durationEditable' => $canEdit,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => $event->eventType->color === '#2BD9FB' && $event->status === CalendarEventStatus::Confirmed ? '#0F172A' : '#FFFFFF',
            'extendedProps' => [
                'type' => $event->eventType->name,
                'status' => $event->status->label(),
                'visibility' => $event->visibility->label(),
                'location' => $event->location,
                'scope' => $event->church?->name ?? $event->area->name,
                'scheduleUrl' => route('calendar.events.schedule', $event),
            ],
        ];
    }
}
