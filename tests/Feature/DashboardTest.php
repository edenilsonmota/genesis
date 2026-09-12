<?php

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

    $this->actingAs($user)->get(route('dashboard'))->assertSee('Administrador global');
});

it('invalidates a session after user or cargo access is revoked', function () {
    $user = User::factory()->create();
    grantPermissionToUser($user, 'dashboard');
    $user->member->positionAssignments()->update(['status' => 'inactive', 'ended_at' => today()]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});
