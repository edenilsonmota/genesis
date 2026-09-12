<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\State;
use App\Models\User;

it('allows a global administrator to access the unified organization page', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->get(route('organization.index'));

    $response
        ->assertSee('Área e Igrejas')
        ->assertSee('Cadastrar área')
        ->assertSee('Cadastre a área primeiro.');
});

it('redirects a visitor to login', function () {
    $response = $this->get(route('organization.index'));

    $response->assertRedirect(route('login'));
});

it('returns 403 to an authenticated user without global permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('organization.index'));

    $response->assertForbidden();
});

it('protects every organization write route in the backend', function () {
    $area = Area::factory()->create();
    $state = State::factory()->create();
    $city = City::factory()->for($state)->create();
    $church = Church::factory()->for($area)->for($city)->create();
    $user = User::factory()->create();

    $responses = [
        $this->actingAs($user)->post(route('organization.area.store'), []),
        $this->actingAs($user)->put(route('organization.area.update', $area), []),
        $this->actingAs($user)->post(route('organization.churches.store'), []),
        $this->actingAs($user)->put(route('organization.churches.update', $church), []),
        $this->actingAs($user)->patch(route('organization.churches.inactivate', $church)),
        $this->actingAs($user)->get(route('organization.cities.index', $state)),
        $this->actingAs($user)->getJson(route('organization.postal-codes.show', '01310100')),
    ];

    foreach ($responses as $response) {
        $response->assertForbidden();
    }
});

it('redirects a global administrator with a temporary password to password change', function () {
    $administrator = User::factory()->globalAdministrator()->requiringPasswordChange()->create();

    $response = $this->actingAs($administrator)->get(route('organization.index'));

    $response->assertRedirect(route('password.change.edit'));
});
