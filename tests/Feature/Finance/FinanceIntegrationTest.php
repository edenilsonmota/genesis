<?php

use App\Models\Area;
use App\Models\PermissionModule;
use App\Models\Position;
use App\Models\User;
use App\PermissionLevel;
use Database\Seeders\PermissionModuleSeeder;
use Illuminate\Support\Facades\Route;

it('seeds every financial permission module idempotently', function () {
    $this->seed(PermissionModuleSeeder::class);
    $this->seed(PermissionModuleSeeder::class);

    $financialModules = PermissionModule::query()->where('key', 'like', 'finance.%')->pluck('category', 'key');

    expect($financialModules->all())->toMatchArray([
        'finance.overview' => 'Financeiro',
        'finance.transactions' => 'Financeiro',
        'finance.tithes' => 'Financeiro',
        'finance.accounts' => 'Financeiro',
        'finance.categories' => 'Financeiro',
        'finance.reports' => 'Financeiro',
    ])->and($financialModules)->toHaveCount(6);
});

it('shows financial capabilities in the position permission matrix', function () {
    $this->seed(PermissionModuleSeeder::class);
    $area = Area::factory()->create();
    $position = Position::factory()->for($area)->grantingAccess()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->get(route('positions.permissions', $position))
        ->assertOk()
        ->assertSee('Financeiro')
        ->assertSee('Contas financeiras')
        ->assertSee('Categorias financeiras')
        ->assertSee('Visão financeira')
        ->assertSee('Dízimos');
});

it('shows only the implemented finance sidebar item when either read permission is available', function () {
    $categoryReader = userWithPermission('finance.categories', PermissionLevel::Read);

    $this->actingAs($categoryReader)->get(route('finance.categories.index'))
        ->assertOk()
        ->assertSee('Financeiro')
        ->assertSee('Contas e categorias')
        ->assertSee('href="'.route('finance.categories.index').'"', false)
        ->assertDontSee('Visão financeira')
        ->assertDontSee('Movimentações')
        ->assertDontSee('Dízimos')
        ->assertDontSee('Relatórios');
});

it('hides the finance sidebar from users without either implemented permission', function () {
    $dashboardUser = userWithPermission('dashboard');

    $this->actingAs($dashboardUser)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Financeiro')
        ->assertDontSee('Contas e categorias');
});

it('does not expose deletion or future finance routes', function () {
    expect(Route::has('finance.accounts.destroy'))->toBeFalse()
        ->and(Route::has('finance.categories.destroy'))->toBeFalse()
        ->and(Route::has('finance.overview.index'))->toBeFalse()
        ->and(Route::has('finance.transactions.index'))->toBeFalse()
        ->and(Route::has('finance.tithes.index'))->toBeFalse()
        ->and(Route::has('finance.reports.index'))->toBeFalse();
});
