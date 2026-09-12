<?php

use App\Models\AccessRole;
use App\Models\User;
use App\Models\UserGlobalAccessRole;

it('redirects a visitor to login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

it('renders the authenticated user identity', function () {
    $user = User::factory()->create([
        'display_name' => 'Ana Oliveira',
        'username' => 'ana.oliveira',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertSee('Ana Oliveira')
        ->assertSee('@ana.oliveira')
        ->assertDontSee('Administrador global');
});

it('identifies an effective global administrator', function () {
    $user = User::factory()->create();
    $role = AccessRole::factory()->globalAdministrator()->create([
        'name' => 'Administrador global',
    ]);
    UserGlobalAccessRole::factory()->for($user)->for($role, 'accessRole')->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertSee('Administrador global');
});

it('invalidates an authenticated session after the user is inactivated', function () {
    $user = User::factory()->inactive()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
