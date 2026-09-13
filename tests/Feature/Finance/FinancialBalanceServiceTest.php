<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\PermissionLevel;
use App\Services\Finance\FinancialBalanceService;
use App\Services\Finance\FinancialTransactionService;

it('counts only settled movements and keeps transfers neutral in the area consolidation', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $user = userWithPermission('finance.transactions', PermissionLevel::Write, $church);
    $first = FinancialAccount::factory()->forChurch($church)->create();
    $second = FinancialAccount::factory()->forChurch($church)->create();
    $income = FinancialCategory::factory()->for($area)->create(['type' => 'income']);
    $service = app(FinancialTransactionService::class);
    $base = [
        'account_id' => $first->id,
        'category_id' => $income->id,
        'department_id' => null,
        'responsible_member_id' => null,
        'title' => 'Receita',
        'amount' => '100.00',
        'occurred_on' => '2026-09-12',
        'competence_month' => null,
        'payment_method' => 'pix',
        'counterparty_name' => null,
        'description' => null,
    ];
    $service->createIncome([...$base, 'status' => 'draft'], $user);
    $service->createIncome([...$base, 'title' => 'Pendente', 'status' => 'pending'], $user);
    $service->createIncome([...$base, 'title' => 'Liquidada', 'status' => 'settled'], $user);
    $service->createTransfer([
        'source_account_id' => $first->id,
        'destination_account_id' => $second->id,
        'title' => 'Transferência',
        'amount' => '40.00',
        'occurred_on' => '2026-09-12',
        'payment_method' => 'pix',
        'description' => null,
        'status' => 'settled',
    ], $user);

    $balances = app(FinancialBalanceService::class);
    expect($balances->forAccount($first))->toBe('60.00')
        ->and($balances->forAccount($second))->toBe('40.00')
        ->and($balances->consolidatedForArea($area))->toBe('100.00');
});
