<?php

use App\Enums\CalendarEventStatus;
use App\Enums\CalendarEventVisibility;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\CalendarEvent;
use App\Models\CalendarEventType;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\User;
use App\PermissionLevel;

function calendarPayload(Area $area, array $overrides = []): array
{
    return [
        'title' => 'Cruzada Evangelística 2027',
        'calendar_event_type_id' => CalendarEventType::factory()->for($area)->create(['name' => 'Cruzada'])->id,
        'start_date' => '2027-06-15',
        'start_time' => '19:00',
        'end_date' => '2027-06-15',
        'end_time' => '22:00',
        'all_day' => '0',
        'scope_type' => 'church',
        'church_id' => null,
        'department_id' => null,
        'responsible_member_id' => null,
        'location' => 'Templo sede',
        'description' => 'Evento evangelístico.',
        'visibility' => CalendarEventVisibility::Church->value,
        'status' => CalendarEventStatus::Confirmed->value,
        'creator_can_edit' => '1',
        'responsible_can_edit' => '1',
        ...$overrides,
    ];
}

it('protects the agenda with its permission module', function () {
    $reader = userWithPermission('calendar', PermissionLevel::Read);
    $withoutPermission = userWithPermission('dashboard', PermissionLevel::Read);

    $this->actingAs($reader)->get(route('calendar.index'))->assertOk()->assertSee('Agenda');
    $this->actingAs($reader)->get(route('calendar.events.create'))->assertForbidden();
    $this->actingAs($withoutPermission)->get(route('calendar.index'))->assertForbidden();
});

it('renders the event form from a calendar selection with an automatic tooltip id', function () {
    $church = Church::factory()->for(Area::factory())->create();
    $writer = userWithPermission('calendar', PermissionLevel::Write, $church);

    $this->actingAs($writer)->get(route('calendar.events.create', [
        'start' => '2026-09-28T00:00',
        'end' => '2026-09-28T00:00',
        'all_day' => 1,
    ]))
        ->assertOk()
        ->assertSee('Novo evento')
        ->assertSee('aria-describedby="tooltip-', false)
        ->assertSee('data-time-24', false)
        ->assertDontSee('type="time"', false);
});

it('creates and returns a custom event type for the active area', function () {
    $church = Church::factory()->for(Area::factory())->create();
    $writer = userWithPermission('calendar', PermissionLevel::Write, $church);

    $this->actingAs($writer)->postJson(route('calendar.event-types.store'), ['name' => 'Conferência de famílias'])
        ->assertCreated()
        ->assertJsonPath('type.name', 'Conferência de famílias');

    expect(CalendarEventType::query()->where('area_id', $church->area_id)->where('name', 'Conferência de famílias')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'calendar_event_type.created')->where('scope_id', $church->area_id)->exists())->toBeTrue();
});

it('creates a church event with its full details and audit trail', function () {
    $church = Church::factory()->for(Area::factory())->create();
    $writer = userWithPermission('calendar', PermissionLevel::Write, $church);
    $responsible = Member::factory()->create();
    $responsible->memberships()->create([
        'church_id' => $church->id,
        'status' => 'active',
        'is_primary' => true,
        'joined_at' => today(),
    ]);

    $response = $this->actingAs($writer)->post(route('calendar.events.store'), calendarPayload($church->area, [
        'church_id' => $church->id,
        'responsible_member_id' => $responsible->id,
    ]));

    $event = CalendarEvent::query()->sole();
    $response->assertRedirect(route('calendar.events.show', $event));
    expect($event->church_id)->toBe($church->id)
        ->and($event->area_id)->toBe($church->area_id)
        ->and($event->responsible_member_id)->toBe($responsible->id)
        ->and($event->starts_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'))->toBe('15/06/2027 19:00')
        ->and($event->ends_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'))->toBe('15/06/2027 22:00')
        ->and(AuditLog::query()->where('action', 'calendar_event.created')->where('scope_id', $church->id)->exists())->toBeTrue();
});

it('returns only events visible in the active church and visibility rules', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $otherChurch = Church::factory()->for($area)->create();
    $reader = userWithPermission('calendar', PermissionLevel::Read, $church);
    $otherUser = User::factory()->create();
    $period = ['starts_at' => '2027-06-15 22:00:00+00', 'ends_at' => '2027-06-16 01:00:00+00'];

    CalendarEvent::factory()->create([...$period, 'area_id' => $area->id, 'church_id' => $church->id, 'title' => 'Evento visível']);
    CalendarEvent::factory()->create([...$period, 'area_id' => $area->id, 'church_id' => $otherChurch->id, 'title' => 'Outra igreja']);
    CalendarEvent::factory()->create([...$period, 'area_id' => $area->id, 'church_id' => $church->id, 'created_by_user_id' => $otherUser->id, 'title' => 'Evento privado', 'visibility' => CalendarEventVisibility::Private]);
    CalendarEvent::factory()->create([...$period, 'area_id' => $area->id, 'church_id' => $church->id, 'created_by_user_id' => $otherUser->id, 'title' => 'Rascunho alheio', 'status' => CalendarEventStatus::Draft]);
    CalendarEvent::factory()->create([...$period, 'area_id' => $area->id, 'church_id' => null, 'title' => 'Evento geral da área']);

    $response = $this->actingAs($reader)->getJson(route('calendar.feed', [
        'start' => '2027-06-01T00:00:00-03:00',
        'end' => '2027-07-01T00:00:00-03:00',
    ]))->assertOk();

    $titles = collect($response->json())->pluck('title');
    expect($titles)->toContain('Evento visível', 'Evento geral da área')
        ->not->toContain('Outra igreja', 'Evento privado', 'Rascunho alheio');
});

it('stores all-day multi-day events with an exclusive end for FullCalendar', function () {
    $church = Church::factory()->for(Area::factory())->create();
    $writer = userWithPermission('calendar', PermissionLevel::Write, $church);

    $this->actingAs($writer)->post(route('calendar.events.store'), calendarPayload($church->area, [
        'church_id' => $church->id,
        'all_day' => '1',
        'start_date' => '2027-06-15',
        'start_time' => null,
        'end_date' => '2027-06-17',
        'end_time' => null,
    ]))->assertRedirect();

    $event = CalendarEvent::query()->sole();
    expect($event->all_day)->toBeTrue()
        ->and($event->starts_at->setTimezone('America/Sao_Paulo')->toDateString())->toBe('2027-06-15')
        ->and($event->ends_at->setTimezone('America/Sao_Paulo')->toDateString())->toBe('2027-06-18');

    $this->actingAs($writer)->getJson(route('calendar.feed', [
        'start' => '2027-06-01', 'end' => '2027-07-01',
    ]))->assertJsonFragment(['start' => '2027-06-15', 'end' => '2027-06-18', 'allDay' => true]);
});

it('allows a creator with current scope access to edit their own event', function () {
    $church = Church::factory()->for(Area::factory())->create();
    $creator = userWithPermission('calendar', PermissionLevel::Read, $church);
    $event = CalendarEvent::factory()->create([
        'area_id' => $church->area_id,
        'church_id' => $church->id,
        'created_by_user_id' => $creator->id,
        'creator_can_edit' => true,
    ]);

    $this->actingAs($creator)->put(route('calendar.events.update', $event), calendarPayload($church->area, [
        'church_id' => $church->id,
        'title' => 'Evento corrigido pelo criador',
    ]))->assertRedirect(route('calendar.events.show', $event));

    expect($event->refresh()->title)->toBe('Evento corrigido pelo criador');
});

it('honors department visibility for active assignments', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $department = Department::factory()->for($area)->create();
    $departmentReader = userWithPermission('calendar', PermissionLevel::Read, $church);
    $departmentReader->member->positionAssignments()->firstOrFail()->position()->update(['department_id' => $department->id]);
    $regularReader = userWithPermission('calendar', PermissionLevel::Read, $church);
    CalendarEvent::factory()->create([
        'area_id' => $area->id,
        'church_id' => $church->id,
        'department_id' => $department->id,
        'visibility' => CalendarEventVisibility::Department,
        'title' => 'Reunião do departamento',
        'starts_at' => '2027-06-15 22:00:00+00',
        'ends_at' => '2027-06-16 01:00:00+00',
    ]);
    $query = ['start' => '2027-06-01T00:00:00-03:00', 'end' => '2027-07-01T00:00:00-03:00'];

    $this->actingAs($departmentReader)->getJson(route('calendar.feed', $query))->assertJsonFragment(['title' => 'Reunião do departamento']);
    $this->actingAs($regularReader)->getJson(route('calendar.feed', $query))->assertJsonMissing(['title' => 'Reunião do departamento']);
});

it('allows authorized drag rescheduling and preserves cancelled events', function () {
    $church = Church::factory()->for(Area::factory())->create();
    $writer = userWithPermission('calendar', PermissionLevel::Write, $church);
    $event = CalendarEvent::factory()->create([
        'area_id' => $church->area_id,
        'church_id' => $church->id,
        'starts_at' => '2027-06-15 22:00:00+00',
        'ends_at' => '2027-06-16 01:00:00+00',
    ]);

    $this->actingAs($writer)->patchJson(route('calendar.events.schedule', $event), [
        'start' => '2027-06-20T18:00:00-03:00',
        'end' => '2027-06-20T21:00:00-03:00',
        'all_day' => false,
    ])->assertOk();
    expect($event->refresh()->starts_at->toIso8601String())->toContain('2027-06-20T21:00:00');
    expect(AuditLog::query()->where('action', 'calendar_event.rescheduled')->exists())->toBeTrue();

    $this->actingAs($writer)->patch(route('calendar.events.cancel', $event))->assertRedirect();
    expect($event->refresh()->status)->toBe(CalendarEventStatus::Cancelled);
    $this->actingAs($writer)->patchJson(route('calendar.events.schedule', $event), [
        'start' => '2027-06-21T18:00:00-03:00',
        'end' => '2027-06-21T21:00:00-03:00',
        'all_day' => false,
    ])->assertForbidden();
});

it('lets a global administrator manage area-wide events from the overview', function () {
    $area = Area::factory()->create(['name' => 'Área Genesis']);
    Church::factory()->for($area)->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->withSession(['active_church_id' => '__overview__'])
        ->post(route('calendar.events.store'), calendarPayload($area, ['scope_type' => 'area']))
        ->assertRedirect();

    $event = CalendarEvent::query()->sole();
    expect($event->area_id)->toBe($area->id)->and($event->church_id)->toBeNull();
    $this->actingAs($administrator)->withSession(['active_church_id' => '__overview__'])
        ->getJson(route('calendar.feed', ['start' => '2027-06-01', 'end' => '2027-07-01']))
        ->assertJsonFragment(['title' => 'Cruzada Evangelística 2027']);
});
