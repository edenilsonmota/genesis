<?php

namespace App\Http\Controllers;

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventVisibility;
use App\Http\Requests\Calendar\StoreCalendarEventRequest;
use App\Http\Requests\Calendar\UpdateCalendarEventRequest;
use App\Models\Area;
use App\Models\CalendarEvent;
use App\Models\CalendarEventType;
use App\Models\Department;
use App\Models\Member;
use App\Services\CalendarEventService;
use App\Services\CalendarEventTypeService;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CalendarEventController extends Controller
{
    public function __construct(private CalendarEventTypeService $eventTypes) {}

    public function index(Request $request, PermissionService $permissions): View
    {
        Gate::authorize('viewAny', CalendarEvent::class);
        $church = $permissions->currentChurch($request->user());
        $areaId = $church?->area_id ?? Area::query()->value('id');
        if ($areaId !== null) {
            $this->eventTypes->ensureForArea(Area::query()->findOrFail($areaId));
        }

        return view('calendar.index', [
            'church' => $church,
            'types' => CalendarEventType::query()->where('area_id', $areaId)->orderBy('name')->get(),
            'statuses' => CalendarEventStatus::cases(),
            'departments' => Department::query()->active()->when($areaId, fn (Builder $query): Builder => $query->where('area_id', $areaId))->orderBy('name')->get(),
            'canCreate' => Gate::allows('create', CalendarEvent::class),
        ]);
    }

    public function create(Request $request, PermissionService $permissions): View
    {
        Gate::authorize('create', CalendarEvent::class);
        $formData = $this->formData($request, $permissions);

        return view('calendar.create', [
            ...$formData,
            'calendarEvent' => new CalendarEvent([
                'calendar_event_type_id' => $formData['types']->firstWhere('name', 'Reunião')?->id ?? $formData['types']->first()?->id,
                'visibility' => CalendarEventVisibility::Church,
                'status' => CalendarEventStatus::Confirmed,
                'all_day' => $request->boolean('all_day'),
                'creator_can_edit' => true,
                'responsible_can_edit' => true,
            ]),
            'startDate' => $request->date('start')?->format('Y-m-d') ?? today()->toDateString(),
            'startTime' => $request->date('start')?->format('H:i') ?? '19:00',
            'endDate' => $request->date('end')?->format('Y-m-d') ?? today()->toDateString(),
            'endTime' => $request->date('end')?->format('H:i') ?? '21:00',
        ]);
    }

    public function store(StoreCalendarEventRequest $request, CalendarEventService $events): RedirectResponse
    {
        $event = $events->create($request->validated(), $request->user());

        return redirect()->route('calendar.events.show', $event)->with('success', 'Evento criado com sucesso.');
    }

    public function show(CalendarEvent $calendarEvent): View
    {
        Gate::authorize('view', $calendarEvent);
        $calendarEvent->load(['area', 'church', 'eventType', 'department', 'responsibleMember', 'createdByUser']);

        return view('calendar.show', compact('calendarEvent'));
    }

    public function edit(Request $request, CalendarEvent $calendarEvent, PermissionService $permissions): View
    {
        Gate::authorize('update', $calendarEvent);
        $timezone = config('genesis.calendar.timezone');
        $start = $calendarEvent->starts_at->setTimezone($timezone);
        $end = $calendarEvent->ends_at->setTimezone($timezone);
        if ($calendarEvent->all_day) {
            $end = $end->subDay();
        }

        return view('calendar.edit', [
            ...$this->formData($request, $permissions, $calendarEvent),
            'calendarEvent' => $calendarEvent,
            'startDate' => $start->format('Y-m-d'),
            'startTime' => $start->format('H:i'),
            'endDate' => $end->format('Y-m-d'),
            'endTime' => $end->format('H:i'),
        ]);
    }

    public function update(UpdateCalendarEventRequest $request, CalendarEvent $calendarEvent, CalendarEventService $events): RedirectResponse
    {
        $events->update($calendarEvent, $request->validated(), $request->user());

        return redirect()->route('calendar.events.show', $calendarEvent)->with('success', 'Evento atualizado com sucesso.');
    }

    public function cancel(CalendarEvent $calendarEvent, CalendarEventService $events): RedirectResponse
    {
        Gate::authorize('cancel', $calendarEvent);
        $events->cancel($calendarEvent);

        return redirect()->route('calendar.events.show', $calendarEvent)->with('success', 'Evento cancelado e mantido no histórico.');
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, PermissionService $permissions, ?CalendarEvent $event = null): array
    {
        $actor = $request->user();
        $currentChurch = $permissions->currentChurch($actor);
        $area = $currentChurch
            ? Area::query()->findOrFail($currentChurch->area_id)
            : Area::query()->firstOrFail();
        $this->eventTypes->ensureForArea($area);
        $churches = $actor->isGlobalAdministrator()
            ? $permissions->availableChurches($actor)->where('area_id', $area->id)->values()
            : collect([$currentChurch])->filter();
        $memberChurchId = $event?->church_id
            ?? ($request->string('church_id')->toString() ?: $currentChurch?->id);

        $members = Member::query()->where('status', Status::Active->value)
            ->whereHas('memberships', fn (Builder $query): Builder => $query
                ->effectiveOn(today()->toDateString())
                ->whereHas('church', fn (Builder $query): Builder => $query->active()->where('area_id', $area->id))
                ->when(! $actor->isGlobalAdministrator() && $memberChurchId, fn (Builder $query) => $query->where('church_id', $memberChurchId)))
            ->with(['memberships' => fn ($query) => $query->effectiveOn(today()->toDateString())->whereHas('church', fn ($query) => $query->where('area_id', $area->id))])
            ->orderBy('name')->get();

        return [
            'area' => $area,
            'churches' => $churches,
            'currentChurch' => $currentChurch,
            'departments' => Department::query()->active()->where('area_id', $area->id)->orderBy('name')->get(),
            'members' => $members,
            'types' => CalendarEventType::query()->where('area_id', $area->id)->orderBy('name')->get(),
            'visibilities' => CalendarEventVisibility::cases(),
            'statuses' => [CalendarEventStatus::Confirmed, CalendarEventStatus::Draft],
        ];
    }
}
