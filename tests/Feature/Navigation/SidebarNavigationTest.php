<?php

use App\Models\User;

it('keeps visitors out and renders the accessible navigation for an authorized user', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $user = userWithPermission('dashboard');

    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertSee('Principal')->assertSee('Visão geral')
        ->assertSee('src="'.asset('images/logo.png').'"', false)
        ->assertSee('id="mobile-sidebar"', false)
        ->assertSee('aria-controls="mobile-sidebar"', false);
});

it('shows the implemented categories and routes to a global administrator', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->get(route('dashboard'))->assertOk()
        ->assertSee('Cadastros')->assertSee('Área e Igrejas')->assertSee('Membros')
        ->assertSee('Administração')->assertSee('Usuários')->assertSee('Cargos e permissões')->assertSee('Departamentos')
        ->assertSee('href="'.route('positions.index').'"', false)
        ->assertSee('href="'.route('departments.index').'"', false)
        ->assertSee('Financeiro')->assertSee('Contas e categorias')
        ->assertSee('href="'.route('finance.accounts.index').'"', false);
});

it('keeps each administration item active only on its own routes', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    foreach ([route('users.index'), route('positions.index'), route('departments.index')] as $url) {
        $response = $this->actingAs($administrator)->get($url);
        $response->assertOk();
        expect($response->getContent())->toMatch('/href="'.preg_quote($url, '/').'"[^>]*aria-current="page"/')
            ->and($response->getContent())->toMatch('/data-sidebar-category="administration"[^>]*aria-expanded="true"/');
    }
});

it('keeps the finance category active on accounts and categories routes', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    foreach ([route('finance.accounts.index'), route('finance.categories.index')] as $url) {
        $content = $this->actingAs($administrator)->get($url)->assertOk()->getContent();

        expect($content)->toMatch('/data-sidebar-category="finance"[^>]*aria-expanded="true"/')
            ->toMatch('/href="'.preg_quote(route('finance.accounts.index'), '/').'"[^>]*aria-current="page"/');
    }
});

it('uses one navigation source with desktop hover expansion and mobile drawer', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $content = $this->actingAs($administrator)->get(route('dashboard'))->getContent();

    expect(substr_count($content, '<nav id="main-navigation"'))->toBe(1)
        ->and($content)->toContain('data-navigation-panel')->toContain('data-navigation-toggle')
        ->toContain('data-sidebar-hover-expand')->toContain('data-sidebar-category-toggle')
        ->toContain('data-mobile-sidebar')->toContain('data-header-user')->not->toContain('data-sidebar-toggle');
});
