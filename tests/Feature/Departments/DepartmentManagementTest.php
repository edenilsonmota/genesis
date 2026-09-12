<?php

use App\Models\Area;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\PermissionLevel;

it('enforces department read and write authorization', function () {
    $reader = userWithPermission('departments', PermissionLevel::Read);

    $this->actingAs($reader)->get(route('departments.index'))->assertOk();
    $this->actingAs($reader)->get(route('departments.create'))->assertForbidden();
    $this->actingAs($reader)->post(route('departments.store'), [])->assertForbidden();
});

it('creates edits and inactivates a department in the singleton area without church selection', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();

    $this->actingAs($administrator)->post(route('departments.store'), ['name' => 'Jovens', 'description' => 'Ministério de jovens'])->assertRedirect();
    $department = Department::query()->sole();
    expect($department->area_id)->toBe($area->id);
    $this->actingAs($administrator)->put(route('departments.update', $department), ['name' => 'Juventude', 'description' => null])->assertRedirect(route('departments.index'));
    $this->actingAs($administrator)->patch(route('departments.status', $department), ['status' => 'inactive'])->assertRedirect();
    expect($department->refresh()->name)->toBe('JUVENTUDE')->and($department->status->value)->toBe('inactive');
});

it('creates a department through the authorized JSON endpoint used by the quick position form', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();

    $this->actingAs($administrator)->postJson(route('departments.store'), ['name' => 'Comunicação'])
        ->assertCreated()
        ->assertJsonPath('department.name', 'COMUNICAÇÃO');

    expect(Department::query()->sole()->area_id)->toBe($area->id);
});

it('keeps case-insensitive department names unique across all statuses', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    Department::factory()->for($area)->create(['name' => 'Louvor', 'status' => 'inactive']);

    $this->actingAs($administrator)->post(route('departments.store'), ['name' => '  louvor  '])->assertSessionHasErrors('name');
    expect(Department::query()->count())->toBe(1);
});

it('normalizes department names to uppercase before persistence', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    Area::factory()->create();

    $this->actingAs($administrator)->post(route('departments.store'), ['name' => '  Escola   Bíblica  '])->assertRedirect();

    expect(Department::query()->sole()->name)->toBe('ESCOLA BÍBLICA');
});

it('preserves positions and assignments when a department is inactivated', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $department = Department::factory()->for($area)->create();
    $position = Position::factory()->for($area)->for($department)->create();

    $this->actingAs($administrator)->patch(route('departments.status', $department), ['status' => 'inactive'])->assertRedirect();

    expect($position->refresh()->department_id)->toBe($department->id)->and(Department::query()->whereKey($department)->exists())->toBeTrue();
});
