<?php

namespace App\Services;

use App\Enums\CalendarEventStatus;
use App\Models\Area;
use App\Models\CalendarEvent;
use App\Models\CalendarEventType;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\User;
use App\Status;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CalendarEventService
{
    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $actor): CalendarEvent
    {
        return DB::transaction(function () use ($attributes, $actor): CalendarEvent {
            [$area, $church] = $this->resolveScope($attributes, $actor);
            $payload = $this->payload($attributes, $area, $church);
            $event = CalendarEvent::query()->create([
                ...$payload,
                'created_by_user_id' => $actor->id,
            ]);
            $this->recordAudit('calendar_event.created', $event, ['title' => $event->title]);

            return $event->refresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(CalendarEvent $event, array $attributes, User $actor): CalendarEvent
    {
        return DB::transaction(function () use ($event, $attributes, $actor): CalendarEvent {
            $locked = CalendarEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['event' => 'Um evento cancelado não pode ser alterado.']);
            }

            [$area, $church] = $this->resolveScope($attributes, $actor);
            $before = $locked->only(['title', 'calendar_event_type_id', 'starts_at', 'ends_at', 'all_day', 'church_id', 'department_id', 'responsible_member_id', 'visibility', 'status']);
            $locked->update($this->payload($attributes, $area, $church));
            $this->recordAudit('calendar_event.updated', $locked, [
                'before' => $before,
                'after' => $locked->only(array_keys($before)),
            ]);

            return $locked->refresh();
        });
    }

    public function reschedule(CalendarEvent $event, string $start, string $end, bool $allDay): CalendarEvent
    {
        return DB::transaction(function () use ($event, $start, $end, $allDay): CalendarEvent {
            $locked = CalendarEvent::query()->lockForUpdate()->findOrFail($event->id);
            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['event' => 'Um evento cancelado não pode ser reagendado.']);
            }

            $period = $this->calendarPeriod($start, $end, $allDay);
            $before = $locked->only(['starts_at', 'ends_at', 'all_day']);
            $locked->update([...$period, 'all_day' => $allDay]);
            $this->recordAudit('calendar_event.rescheduled', $locked, [
                'before' => $before,
                'after' => $locked->only(['starts_at', 'ends_at', 'all_day']),
            ]);

            return $locked->refresh();
        });
    }

    public function cancel(CalendarEvent $event): CalendarEvent
    {
        return DB::transaction(function () use ($event): CalendarEvent {
            $locked = CalendarEvent::query()->lockForUpdate()->findOrFail($event->id);
            if (! $locked->isCancelled()) {
                $locked->update(['status' => CalendarEventStatus::Cancelled]);
                $this->recordAudit('calendar_event.cancelled', $locked, ['title' => $locked->title]);
            }

            return $locked->refresh();
        });
    }

    /** @param array<string, mixed> $attributes
     * @return array{0: Area, 1: ?Church}
     */
    private function resolveScope(array $attributes, User $actor): array
    {
        if (! $actor->isGlobalAdministrator()) {
            $church = $this->permissions->currentChurch($actor);
            abort_unless($church !== null, 403);

            return [Area::query()->findOrFail($church->area_id), $church];
        }

        if (($attributes['scope_type'] ?? 'area') === 'church') {
            $church = Church::query()->active()->findOrFail($attributes['church_id'] ?? null);

            return [Area::query()->findOrFail($church->area_id), $church];
        }

        return [Area::query()->firstOrFail(), null];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function payload(array $attributes, Area $area, ?Church $church): array
    {
        $eventType = CalendarEventType::query()
            ->where('area_id', $area->id)
            ->find($attributes['calendar_event_type_id']);
        if ($eventType === null) {
            throw ValidationException::withMessages(['calendar_event_type_id' => 'O tipo de evento não pertence à área selecionada.']);
        }

        $department = filled($attributes['department_id'] ?? null)
            ? Department::query()->findOrFail($attributes['department_id'])
            : null;
        if ($department !== null && $department->area_id !== $area->id) {
            throw ValidationException::withMessages(['department_id' => 'O departamento não pertence à área do evento.']);
        }

        $responsible = filled($attributes['responsible_member_id'] ?? null)
            ? Member::query()->where('status', Status::Active->value)->findOrFail($attributes['responsible_member_id'])
            : null;
        if ($responsible !== null && ! $this->memberBelongsToScope($responsible, $area, $church)) {
            throw ValidationException::withMessages(['responsible_member_id' => 'O responsável não possui vínculo ativo com o escopo do evento.']);
        }

        $period = $this->formPeriod($attributes);

        return [
            'area_id' => $area->id,
            'church_id' => $church?->id,
            'department_id' => $department?->id,
            'responsible_member_id' => $responsible?->id,
            'title' => trim((string) $attributes['title']),
            'calendar_event_type_id' => $eventType->id,
            ...$period,
            'all_day' => (bool) $attributes['all_day'],
            'location' => filled($attributes['location'] ?? null) ? trim((string) $attributes['location']) : null,
            'description' => filled($attributes['description'] ?? null) ? trim((string) $attributes['description']) : null,
            'visibility' => $attributes['visibility'],
            'status' => $attributes['status'],
            'creator_can_edit' => (bool) ($attributes['creator_can_edit'] ?? false),
            'responsible_can_edit' => (bool) ($attributes['responsible_can_edit'] ?? false),
        ];
    }

    private function memberBelongsToScope(Member $member, Area $area, ?Church $church): bool
    {
        return $member->memberships()->effectiveOn(today()->toDateString())
            ->when($church, fn ($query) => $query->where('church_id', $church->id))
            ->whereHas('church', fn ($query) => $query->active()->where('area_id', $area->id))
            ->exists();
    }

    /** @param array<string, mixed> $attributes
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}
     */
    private function formPeriod(array $attributes): array
    {
        $timezone = config('genesis.calendar.timezone');
        $allDay = (bool) $attributes['all_day'];
        $start = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $attributes['start_date'].' '.($allDay ? '00:00' : $attributes['start_time']),
            $timezone,
        );
        $end = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $attributes['end_date'].' '.($allDay ? '00:00' : $attributes['end_time']),
            $timezone,
        );
        if ($allDay) {
            $end = $end->addDay();
        }

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['end_date' => 'O término deve ser posterior ao início.']);
        }

        return ['starts_at' => $start->utc(), 'ends_at' => $end->utc()];
    }

    /** @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable} */
    private function calendarPeriod(string $start, string $end, bool $allDay): array
    {
        $timezone = config('genesis.calendar.timezone');
        $startsAt = CarbonImmutable::parse($start, $timezone);
        $endsAt = CarbonImmutable::parse($end, $timezone);
        if ($allDay) {
            $startsAt = $startsAt->setTimezone($timezone)->startOfDay();
            $endsAt = $endsAt->setTimezone($timezone)->startOfDay();
        }
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['end' => 'O término deve ser posterior ao início.']);
        }

        return ['starts_at' => $startsAt->utc(), 'ends_at' => $endsAt->utc()];
    }

    /** @param array<string, mixed> $details */
    private function recordAudit(string $action, CalendarEvent $event, array $details): void
    {
        $this->audit->record(
            $action,
            'calendar_events',
            $event,
            $event->church_id ? 'church' : 'area',
            $event->church_id ?? $event->area_id,
            $details,
        );
    }
}
