<?php

use App\Models\Member;
use App\Models\User;

it('shows the dashboard link to authenticated users and keeps visitors out of the authenticated layout', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('Principal')
        ->assertSee('Visão geral')
        ->assertSee('href="'.route('dashboard').'"', false)
        ->assertSee('aria-current="page"', false)
        ->assertDontSee('Cadastros')
        ->assertDontSee('href="'.route('organization.index').'"', false)
        ->assertDontSee('href="'.route('members.index').'"', false);
});

it('renders the official logo and an accessible mobile navigation drawer', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('src="'.asset('images/logo.png').'"', false)
        ->assertSee('alt="Logo Genesis+"', false)
        ->assertSee('id="mobile-sidebar"', false)
        ->assertSee('aria-controls="mobile-sidebar"', false)
        ->assertSee('data-mobile-sidebar', false)
        ->assertSee('data-mobile-sidebar-close', false);
});

it('shows the implemented registrations to a global administrator using the existing routes', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('Cadastros')
        ->assertSee('Área e Igrejas')
        ->assertSee('Membros')
        ->assertSee('href="'.route('organization.index').'"', false)
        ->assertSee('href="'.route('members.index').'"', false)
        ->assertDontSee('Em breve')
        ->assertDontSee('Grupos de acesso');
});

it('keeps the organization item active on its internal route', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $response = $this->actingAs($administrator)->get(route('organization.index'));

    $response->assertOk();
    expect($response->getContent())->toMatch(
        '/href="'.preg_quote(route('organization.index'), '/').'"[^>]*aria-current="page"/',
    )->and($response->getContent())->toMatch(
        '/data-sidebar-category="registrations"[^>]*aria-expanded="true"/',
    );
});

it('keeps the members item active on create show and edit routes', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $member = Member::factory()->create();

    foreach ([
        route('members.create'),
        route('members.show', $member),
        route('members.edit', $member),
    ] as $url) {
        $response = $this->actingAs($administrator)->get($url);

        $response->assertOk();
        expect($response->getContent())->toMatch(
            '/href="'.preg_quote(route('members.index'), '/').'"[^>]*aria-current="page"/',
        )->and($response->getContent())->toMatch(
            '/data-sidebar-category="registrations"[^>]*aria-expanded="true"/',
        );
    }
});

it('uses one navigation source with desktop hover expansion and the mobile drawer', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $response = $this->actingAs($administrator)->get(route('dashboard'));
    $content = $response->getContent();

    expect(substr_count($content, '<nav id="main-navigation"'))->toBe(1)
        ->and($content)->toContain('data-navigation-panel')
        ->and($content)->toContain('data-navigation-toggle')
        ->and($content)->toContain('data-sidebar-hover-expand')
        ->and($content)->toContain('data-sidebar-category-toggle')
        ->and($content)->toContain('data-mobile-sidebar')
        ->and($content)->toContain('data-header-user')
        ->and($content)->not->toContain('data-sidebar-toggle')
        ->and($content)->not->toContain('data-sidebar-user-details');
});
