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
        'finance.reports' => 'Financeiro',
    ])->and($financialModules)->toHaveCount(4);
});

it('shows financial capabilities in the position permission matrix', function () {
    $this->seed(PermissionModuleSeeder::class);
    $area = Area::factory()->create();
    $position = Position::factory()->for($area)->grantingAccess()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->get(route('positions.permissions', $position))
        ->assertOk()
        ->assertSee('Financeiro')
        ->assertSee('Visão financeira')
        ->assertSee('Dízimos');
});

it('shows only the movements item when its read permission is available', function () {
    $transactionReader = userWithPermission('finance.transactions', PermissionLevel::Read);

    $this->actingAs($transactionReader)->get(route('finance.transactions.index'))
        ->assertOk()
        ->assertSee('Financeiro')
        ->assertSee('Movimentações')
        ->assertSee('href="'.route('finance.transactions.index').'"', false)
        ->assertDontSee('Visão financeira')
        ->assertDontSee('Dízimos')
        ->assertDontSee('Relatórios');
});

it('hides the finance sidebar from users without either implemented permission', function () {
    $dashboardUser = userWithPermission('dashboard');

    $this->actingAs($dashboardUser)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Financeiro')
        ->assertDontSee('Movimentações');
});

it('exposes movements without deletion and keeps future finance routes private', function () {
    expect(Route::has('finance.accounts.index'))->toBeFalse()
        ->and(Route::has('finance.categories.index'))->toBeFalse()
        ->and(Route::has('finance.overview.index'))->toBeFalse()
        ->and(Route::has('finance.transactions.index'))->toBeTrue()
        ->and(Route::has('finance.transactions.destroy'))->toBeFalse()
        ->and(Route::has('finance.tithes.index'))->toBeFalse()
        ->and(Route::has('finance.reports.index'))->toBeFalse();
});
