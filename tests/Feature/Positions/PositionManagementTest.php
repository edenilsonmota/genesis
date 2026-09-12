<?php

use App\Models\Area;
use App\Models\Department;
use App\Models\PermissionModule;
use App\Models\Position;
use App\Models\PositionPermission;
use App\Models\User;
use App\PermissionLevel;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\PositionService;

it('enforces position read and write authorization in the backend', function () {
    $reader = userWithPermission('positions', PermissionLevel::Read);

    $this->actingAs($reader)->get(route('positions.index'))->assertOk();
    $this->actingAs($reader)->get(route('positions.create'))->assertForbidden();
    $this->actingAs($reader)->post(route('positions.store'), [])->assertForbidden();
});

it('renders the searchable department selector and authorized quick creation modal', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->get(route('positions.create'))
        ->assertOk()
        ->assertSee('data-department-select', false)
        ->assertSee('data-quick-department-trigger', false)
        ->assertSee('data-quick-department-modal', false);
});

it('creates and edits a position in the singleton area with an optional active department', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $department = Department::factory()->for($area)->create();

    $this->actingAs($administrator)->post(route('positions.store'), [
        'name' => 'Coordenador', 'description' => 'Coordena equipes.', 'department_id' => $department->id, 'grants_system_access' => '1',
    ])->assertRedirect();
    $position = Position::query()->sole();
    expect($position->area_id)->toBe($area->id)->and($position->grants_system_access)->toBeTrue();

    $this->actingAs($administrator)->put(route('positions.update', $position), [
        'name' => 'Coordenador geral', 'description' => null, 'department_id' => '', 'grants_system_access' => '1',
    ])->assertRedirect(route('positions.index'));
    expect($position->refresh()->name)->toBe('COORDENADOR GERAL')->and($position->department_id)->toBeNull();
});

it('enforces case-insensitive position uniqueness within area and department', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $first = Department::factory()->for($area)->create(['name' => 'Jovens']);
    $second = Department::factory()->for($area)->create(['name' => 'Infantil']);
    Position::factory()->for($area)->for($first)->create(['name' => 'Coordenador']);

    $this->actingAs($administrator)->post(route('positions.store'), ['name' => 'coordenador', 'department_id' => $first->id])->assertSessionHasErrors('name');
    $this->actingAs($administrator)->post(route('positions.store'), ['name' => 'Coordenador', 'department_id' => $second->id])->assertRedirect();
    expect(Position::query()->count())->toBe(2);
});

it('normalizes position names to uppercase before persistence', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    Area::factory()->create();

    $this->actingAs($administrator)->post(route('positions.store'), [
        'name' => '  líder   de louvor  ',
    ])->assertRedirect();

    expect(Position::query()->sole()->name)->toBe('LÍDER DE LOUVOR');
});

it('rejects inactive departments and protects fixed positions structurally', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $department = Department::factory()->for($area)->create(['status' => 'inactive']);
    $fixed = Position::factory()->for($area)->fixed()->create();

    $this->actingAs($administrator)->post(route('positions.store'), ['name' => 'Professor', 'department_id' => $department->id])->assertSessionHasErrors('department_id');
    $this->actingAs($administrator)->put(route('positions.update', $fixed), ['name' => 'Alterado'])->assertForbidden();
    $this->actingAs($administrator)->patch(route('positions.status', $fixed), ['status' => 'inactive'])->assertForbidden();
});

it('persists exactly one read or write level per module and removes none entries', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $position = Position::factory()->for($area)->grantingAccess()->create();
    $read = PermissionModule::factory()->create(['key' => 'read-module']);
    $write = PermissionModule::factory()->create(['key' => 'write-module']);
    $none = PermissionModule::factory()->create(['key' => 'none-module']);

    $this->actingAs($administrator)->put(route('positions.permissions.update', $position), ['permissions' => [
        $read->id => 'read', $write->id => 'write', $none->id => 'none',
    ]])->assertRedirect();

    expect(PositionPermission::query()->where('position_id', $position->id)->count())->toBe(2)
        ->and(PositionPermission::query()->where('permission_module_id', $read->id)->value('level'))->toBe(PermissionLevel::Read)
        ->and(PositionPermission::query()->where('permission_module_id', $write->id)->value('level'))->toBe(PermissionLevel::Write);
});

it('does not expose or persist a permission matrix for an organizational position', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $position = Position::factory()->for($area)->create(['grants_system_access' => false]);
    $module = PermissionModule::factory()->create();

    $this->actingAs($administrator)->get(route('positions.permissions', $position))->assertForbidden();
    $this->actingAs($administrator)->put(route('positions.permissions.update', $position), [
        'permissions' => [$module->id => 'write'],
    ])->assertForbidden();
    expect(PositionPermission::query()->where('position_id', $position->id)->exists())->toBeFalse();
});

it('requires impact confirmation and removes permissions when system access is disabled', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    ['position' => $position, 'member' => $member] = eligibleMemberForUser();
    User::factory()->for($member)->create();
    $module = PermissionModule::factory()->create();
    PositionPermission::factory()->for($position)->for($module, 'permissionModule')->create();

    $payload = ['name' => $position->name, 'department_id' => '', 'description' => $position->description];
    $this->actingAs($administrator)->put(route('positions.update', $position), $payload)->assertSessionHasErrors('confirm_access_revocation');
    $this->actingAs($administrator)->put(route('positions.update', $position), $payload + ['confirm_access_revocation' => '1'])->assertRedirect();

    expect($position->refresh()->grants_system_access)->toBeFalse()->and($position->permissions()->count())->toBe(0);
});

it('rolls back permission removal when updating the position cannot be completed', function () {
    $area = Area::factory()->create();
    $position = Position::factory()->for($area)->grantingAccess()->create();
    $module = PermissionModule::factory()->create();
    PositionPermission::factory()->for($position)->for($module, 'permissionModule')->create();
    $audit = Mockery::mock(AuditService::class);
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
    $service = new PositionService($audit, app(PermissionService::class));

    expect(fn () => $service->update($position, [
        'name' => $position->name,
        'department_id' => null,
        'grants_system_access' => false,
    ]))->toThrow(RuntimeException::class);

    expect($position->refresh()->grants_system_access)->toBeTrue()
        ->and($position->permissions()->where('permission_module_id', $module->id)->exists())->toBeTrue();
});

it('protects the last local administrator when a permission is revoked', function () {
    $global = User::factory()->globalAdministrator()->create();
    ['position' => $position, 'member' => $member] = eligibleMemberForUser();
    User::factory()->for($member)->create();
    $usersModule = PermissionModule::factory()->create(['key' => 'users']);
    PositionPermission::factory()->for($position)->for($usersModule, 'permissionModule')->create(['level' => 'write']);

    $this->actingAs($global)->put(route('positions.permissions.update', $position), [
        'permissions' => [$usersModule->id => 'read'], 'confirm_access_revocation' => '1',
    ])->assertSessionHasErrors('position');
    expect($position->permissions()->where('permission_module_id', $usersModule->id)->value('level'))->toBe(PermissionLevel::Write);
});
