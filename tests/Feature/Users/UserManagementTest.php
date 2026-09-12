<?php

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberPositionAssignment;
use App\Models\PermissionModule;
use App\Models\Position;
use App\Models\PositionPermission;
use App\Models\User;
use App\PermissionLevel;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;

it('allows users read to consult and protects every write endpoint', function () {
    $reader = userWithPermission('users', PermissionLevel::Read);

    $this->actingAs($reader)->get(route('users.index'))->assertOk();
    $this->actingAs($reader)->get(route('users.create'))->assertForbidden();
    $this->actingAs($reader)->post(route('users.store'), [])->assertForbidden();
});

it('creates a user from an eligible member with a normalized username and one-time password', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    ['member' => $member] = eligibleMemberForUser();

    $response = $this->actingAs($administrator)->post(route('users.store'), ['member_id' => $member->id, 'username' => '  NOVO.Usuario  ']);

    $response->assertOk()->assertSee('Senha temporária')->assertSee('Ela está somente nesta resposta');
    $response->assertSessionMissing('temporary_password');
    $user = User::query()->where('member_id', $member->id)->sole();
    expect($user->username)->toBe('novo.usuario')->and($user->must_change_password)->toBeTrue()->and(Hash::check('novo.usuario', $user->password))->toBeFalse();
    $audit = AuditLog::query()->where('action', 'user.created')->sole();
    expect($audit->details)->not->toHaveKeys(['password', 'senha', 'hash', 'token']);
});

it('rejects duplicate members case-insensitive usernames reserved names and ineligible members', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    ['member' => $member] = eligibleMemberForUser();
    User::factory()->create(['username' => 'nome.existente']);

    $this->actingAs($administrator)->post(route('users.store'), ['member_id' => $member->id, 'username' => 'NOME.EXISTENTE'])->assertSessionHasErrors('username');
    $this->actingAs($administrator)->post(route('users.store'), ['member_id' => $member->id, 'username' => 'root'])->assertSessionHasErrors('username');
    $ineligible = Member::factory()->create();
    $this->actingAs($administrator)->post(route('users.store'), ['member_id' => $ineligible->id, 'username' => 'sem.cargo'])->assertSessionHasErrors('member_id');

    User::factory()->for($member)->create();
    $this->actingAs($administrator)->post(route('users.store'), ['member_id' => $member->id, 'username' => 'outro.nome'])->assertSessionHasErrors('member_id');
});

it('does not transform a member who has only an organizational position into a user', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $member = Member::factory()->create();
    $membership = MemberChurchMembership::factory()->for($member)->for($church)->create();
    $position = Position::factory()->for($area)->create(['grants_system_access' => false]);
    MemberPositionAssignment::factory()->for($membership, 'membership')->for($position)->create();

    $this->actingAs($administrator)->post(route('users.store'), [
        'member_id' => $member->id,
        'username' => 'somente.organizacional',
    ])->assertSessionHasErrors('member_id');

    expect(User::query()->where('member_id', $member->id)->exists())->toBeFalse();
});

it('rolls back user creation when its audit record cannot be written', function () {
    ['member' => $member] = eligibleMemberForUser();
    $audit = Mockery::mock(AuditService::class);
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
    $service = new UserService($audit, app(PermissionService::class));

    expect(fn () => $service->create($member, 'transacao.segura'))->toThrow(RuntimeException::class);
    expect(User::query()->where('member_id', $member->id)->exists())->toBeFalse();
});

it('does not let a user inactivate itself or alter the protected global administrator', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $otherGlobal = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->patch(route('users.inactivate', $administrator))->assertForbidden();
    $this->actingAs($administrator)->patch(route('users.inactivate', $otherGlobal))->assertForbidden();
    $this->actingAs($administrator)->post(route('users.password.reset', $otherGlobal))->assertForbidden();
});

it('does not inactivate the last valid local administrator of a church', function () {
    $global = User::factory()->globalAdministrator()->create();
    ['member' => $member, 'position' => $position] = eligibleMemberForUser();
    $user = User::factory()->for($member)->create();
    $usersModule = PermissionModule::factory()->create(['key' => 'users']);
    PositionPermission::factory()->for($position)->for($usersModule, 'permissionModule')->create(['level' => 'write']);

    $this->actingAs($global)->patch(route('users.inactivate', $user))->assertSessionHasErrors('status');
    expect($user->refresh()->status->value)->toBe('active');
});

it('revokes an open session as soon as the last access-granting position ends', function () {
    ['member' => $member, 'position' => $position] = eligibleMemberForUser();
    $user = User::factory()->for($member)->create();
    $assignment = $position->assignments()->sole();
    $assignment->update(['status' => 'inactive', 'ended_at' => today()]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('isolates local user details between churches', function () {
    $reader = userWithPermission('users', PermissionLevel::Read);
    $other = eligibleMemberForUser();
    $target = User::factory()->for($other['member'])->create();

    $this->actingAs($reader)->get(route('users.index'))->assertOk()->assertDontSee($target->username);
    $this->actingAs($reader)->get(route('users.show', $target))->assertForbidden();
});

it('isolates every local user management action between churches', function () {
    $administrator = userWithPermission('users', PermissionLevel::Write);
    $other = eligibleMemberForUser();
    $target = User::factory()->for($other['member'])->create();

    $this->actingAs($administrator)->patch(route('users.inactivate', $target))->assertForbidden();
    $this->actingAs($administrator)->patch(route('users.activate', $target))->assertForbidden();
    $this->actingAs($administrator)->post(route('users.password.reset', $target))->assertForbidden();
    $this->actingAs($administrator)->patch(route('users.password.force-change', $target))->assertForbidden();
});
