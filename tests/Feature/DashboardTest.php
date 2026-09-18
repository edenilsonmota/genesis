<?php

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\User;
use App\PermissionLevel;

it('redirects a visitor to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders an eligible authenticated user identity', function () {
    $user = User::factory()->create(['display_name' => 'Ana Oliveira', 'username' => 'ana.oliveira']);
    grantPermissionToUser($user, 'dashboard', PermissionLevel::Read);

    $this->actingAs($user)->get(route('dashboard'))->assertSee('Ana Oliveira')->assertSee('@ana.oliveira')->assertDontSee('Administrador global');
});

it('identifies a protected global administrator', function () {
    $user = User::factory()->globalAdministrator()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Acompanhe toda a estrutura da organização.');
});

it('consolidates churches for the global overview and scopes a local dashboard', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create(['name' => 'Igreja Central']);
    $otherChurch = Church::factory()->for($area)->create(['name' => 'Igreja Bairro']);
    $member = Member::factory()->create();
    $otherMember = Member::factory()->create();
    MemberChurchMembership::factory()->for($member)->for($church)->create();
    MemberChurchMembership::factory()->for($otherMember)->for($otherChurch)->create();
    AuditLog::query()->create(['action' => 'member.created', 'resource' => 'members', 'record_id' => $member->id, 'scope_type' => 'church', 'scope_id' => $church->id, 'details' => ['name' => 'Atividade Central']]);
    AuditLog::query()->create(['action' => 'member.created', 'resource' => 'members', 'record_id' => $otherMember->id, 'scope_type' => 'church', 'scope_id' => $otherChurch->id, 'details' => ['name' => 'Atividade Bairro']]);

    $administrator = User::factory()->globalAdministrator()->create();
    $this->actingAs($administrator)->withSession(['active_church_id' => '__overview__'])->get(route('dashboard'))
        ->assertOk()
        ->assertSee('"name":"Igreja Central","value":1', false)
        ->assertSee('"name":"Igreja Bairro","value":1', false);

    $localUser = userWithPermission('dashboard', PermissionLevel::Read, $church);
    $this->actingAs($localUser)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('"name":"Igreja Central","value":', false)
        ->assertDontSee('"name":"Igreja Bairro","value":', false)
        ->assertSee('Atividade Central')
        ->assertDontSee('Atividade Bairro');
});

it('invalidates a session after user or cargo access is revoked', function () {
    $user = User::factory()->create();
    grantPermissionToUser($user, 'dashboard');
    $user->member->positionAssignments()->update(['status' => 'inactive', 'ended_at' => today()]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
