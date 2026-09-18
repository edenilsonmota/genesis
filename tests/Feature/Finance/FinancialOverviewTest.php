<?php

use App\Enums\FinancialMovementDirection;
use App\Models\Area;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\PermissionLevel;
use App\Services\Finance\FinancialOverviewService;

it('scopes the financial overview to the active church and consolidates it for the global administrator', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $otherChurch = Church::factory()->for($area)->create();
    $account = FinancialAccount::factory()->forChurch($church)->create();
    $otherAccount = FinancialAccount::factory()->forChurch($otherChurch)->create();
    $user = userWithPermission('finance.overview', PermissionLevel::Read, $church);

    foreach ([[$account, 100], [$otherAccount, 900]] as [$targetAccount, $amount]) {
        $transaction = FinancialTransaction::factory()->settled()->create(['amount' => $amount, 'occurred_on' => today()]);
        FinancialMovement::factory()->for($transaction, 'transaction')->for($targetAccount, 'account')->create([
            'direction' => FinancialMovementDirection::Inflow,
            'amount' => $amount,
            'settled_on' => today(),
        ]);
    }

    $this->actingAs($user)->get(route('finance.overview.index'))->assertOk()->assertSee('Visão financeira');
    $churchData = app(FinancialOverviewService::class)->forScope($church, today()->year);
    expect($churchData['summary']['inflows'])->toBe(100.0);

    $administrator = User::factory()->globalAdministrator()->create();
    $this->actingAs($administrator)
        ->withSession(['active_church_id' => '__overview__'])
        ->get(route('finance.overview.index'))
        ->assertOk()
        ->assertSee('Consolidado de todas as áreas e igrejas');
    $globalData = app(FinancialOverviewService::class)->forScope(null, today()->year);
    expect($globalData['summary']['inflows'])->toBe(1000.0);
});

it('forbids the financial overview without its permission', function () {
    $user = userWithPermission('dashboard');
    $this->actingAs($user)->get(route('finance.overview.index'))->assertForbidden();
});
