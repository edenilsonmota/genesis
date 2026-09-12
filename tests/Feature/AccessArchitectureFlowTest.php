<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\PermissionModule;
use App\Models\Position;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;

it('completes the access flow from catalog setup through authorized login', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $dashboardModule = PermissionModule::factory()->create(['key' => 'dashboard', 'category' => 'Principal']);
    $membersModule = PermissionModule::factory()->create(['key' => 'members', 'category' => 'Cadastros']);

    $this->actingAs($administrator)->post(route('departments.store'), [
        'name' => 'Jovens',
        'description' => 'Organização do ministério de jovens.',
    ])->assertRedirect();
    $department = Department::query()->sole();

    $this->post(route('positions.store'), [
        'name' => 'Coordenador',
        'department_id' => $department->id,
        'grants_system_access' => '1',
    ])->assertRedirect();
    $position = Position::query()->sole();

    $this->put(route('positions.permissions.update', $position), [
        'permissions' => [
            $dashboardModule->id => 'read',
            $membersModule->id => 'write',
        ],
    ])->assertRedirect();

    $member = Member::factory()->create();
    $membership = MemberChurchMembership::factory()->for($member)->for($church)->create();
    $this->post(route('members.positions.store', $member), [
        'member_church_membership_id' => $membership->id,
        'position_id' => $position->id,
        'started_at' => today()->toDateString(),
    ])->assertRedirect();

    [$user, $temporaryPassword] = app(UserService::class)->create($member, '  Coordenador.Jovens  ');
    expect($user->username)->toBe('coordenador.jovens')
        ->and($user->must_change_password)->toBeTrue()
        ->and(Hash::check($temporaryPassword, $user->password))->toBeTrue();

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->post(route('login.store'), [
        'username' => $user->username,
        'password' => $temporaryPassword,
    ])->assertRedirect(route('password.change.edit'));

    $this->put(route('password.change.update'), [
        'current_password' => $temporaryPassword,
        'password' => 'PermanentPassword456',
        'password_confirmation' => 'PermanentPassword456',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk();
    $this->get(route('members.index'))->assertOk();
    $this->get(route('users.index'))->assertForbidden();
});
