<?php

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\User;
use App\PermissionLevel;

it('shows the consolidated audit trail to a global administrator', function () {
    $area = Area::factory()->create();
    $central = Church::factory()->for($area)->create(['name' => 'Igreja Central']);
    $bairro = Church::factory()->for($area)->create(['name' => 'Igreja do Bairro']);
    AuditLog::query()->create([
        'actor_name' => 'Administrador Central',
        'actor_username' => 'admin.central',
        'action' => 'church.updated',
        'resource' => 'churches',
        'record_id' => $central->id,
        'scope_type' => 'church',
        'scope_id' => $central->id,
        'details' => ['fields' => ['name']],
    ]);
    AuditLog::query()->create([
        'actor_name' => 'Administrador Bairro',
        'actor_username' => 'admin.bairro',
        'action' => 'member.created',
        'resource' => 'members',
        'scope_type' => 'church',
        'scope_id' => $bairro->id,
        'details' => ['name' => 'Pessoa do Bairro'],
    ]);

    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)
        ->withSession(['active_church_id' => '__overview__'])
        ->get(route('audit.index'))
        ->assertOk()
        ->assertSee('Visão consolidada')
        ->assertSee('Administrador Central')
        ->assertSee('Administrador Bairro')
        ->assertSee('Igreja Central')
        ->assertSee('Igreja do Bairro');
});

it('limits the audit trail to the church selected or assigned to the user', function () {
    $area = Area::factory()->create();
    $central = Church::factory()->for($area)->create(['name' => 'Igreja Central']);
    $bairro = Church::factory()->for($area)->create(['name' => 'Igreja do Bairro']);
    AuditLog::query()->create([
        'actor_name' => 'Ator Central',
        'action' => 'church.updated',
        'resource' => 'churches',
        'scope_type' => 'church',
        'scope_id' => $central->id,
    ]);
    AuditLog::query()->create([
        'actor_name' => 'Ator Bairro',
        'action' => 'church.updated',
        'resource' => 'churches',
        'scope_type' => 'church',
        'scope_id' => $bairro->id,
    ]);
    $centralMember = Member::factory()->create();
    $bairroMember = Member::factory()->create();
    MemberChurchMembership::factory()->for($centralMember)->for($central)->create();
    MemberChurchMembership::factory()->for($bairroMember)->for($bairro)->create();
    AuditLog::query()->create([
        'actor_name' => 'Cadastro Central',
        'action' => 'member.updated',
        'resource' => 'members',
        'record_id' => $centralMember->id,
    ]);
    AuditLog::query()->create([
        'actor_name' => 'Cadastro Bairro',
        'action' => 'member.updated',
        'resource' => 'members',
        'record_id' => $bairroMember->id,
    ]);

    $localUser = userWithPermission('audit', PermissionLevel::Read, $central);
    $this->actingAs($localUser)->get(route('audit.index'))
        ->assertOk()
        ->assertSee('Igreja Central')
        ->assertSee('Ator Central')
        ->assertSee('Cadastro Central')
        ->assertDontSee('Ator Bairro')
        ->assertDontSee('Cadastro Bairro');

    $administrator = User::factory()->globalAdministrator()->create();
    $this->actingAs($administrator)->withSession(['active_church_id' => $bairro->id])->get(route('audit.index'))
        ->assertOk()
        ->assertSee('Ator Bairro')
        ->assertDontSee('Ator Central');
});

it('requires audit permission and applies activity filters', function () {
    $church = Church::factory()->for(Area::factory())->create();
    AuditLog::query()->create([
        'actor_name' => 'Maria Auditora',
        'action' => 'member.created',
        'resource' => 'members',
        'scope_type' => 'church',
        'scope_id' => $church->id,
    ]);
    AuditLog::query()->create([
        'actor_name' => 'João Gestor',
        'action' => 'church.updated',
        'resource' => 'churches',
        'scope_type' => 'church',
        'scope_id' => $church->id,
    ]);

    $reader = userWithPermission('audit', PermissionLevel::Read, $church);
    $this->actingAs($reader)->get(route('audit.index', ['search' => 'Maria', 'resource' => 'members']))
        ->assertOk()
        ->assertSee('Maria Auditora')
        ->assertDontSee('João Gestor');

    $withoutPermission = userWithPermission('dashboard', PermissionLevel::Read, $church);
    $this->actingAs($withoutPermission)->get(route('audit.index'))->assertForbidden();
});
