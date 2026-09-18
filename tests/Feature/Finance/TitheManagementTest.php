<?php

use App\Enums\FinancialTransactionOrigin;
use App\Models\Area;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\User;
use App\PermissionLevel;

it('lists church members and records a tithe only with tithe write permission', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $writer = userWithPermission('finance.tithes', PermissionLevel::Write, $church);
    $reader = userWithPermission('finance.tithes', PermissionLevel::Read, $church);
    $member = Member::factory()->create(['name' => 'Membro Dizimista']);
    MemberChurchMembership::factory()->for($member)->for($church)->create();
    FinancialAccount::factory()->forChurch($church)->create(['is_default' => true]);
    FinancialCategory::factory()->for($area)->create(['name' => 'Dízimos', 'type' => 'income']);

    $this->actingAs($reader)->get(route('finance.tithes.index'))->assertOk()->assertSee('Membro Dizimista')->assertDontSee('Registrar dízimo');
    $this->actingAs($reader)->post(route('finance.tithes.store'), [
        'church_id' => $church->id,
        'member_id' => $member->id,
        'amount' => '100.00',
        'paid_on' => today()->toDateString(),
        'competence_month' => today()->format('Y-m'),
        'payment_method' => 'pix',
    ])->assertForbidden();

    $this->actingAs($writer)->post(route('finance.tithes.store'), [
        'church_id' => $church->id,
        'member_id' => $member->id,
        'amount' => '100.00',
        'paid_on' => today()->toDateString(),
        'competence_month' => today()->format('Y-m'),
        'payment_method' => 'pix',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(FinancialTransaction::query()->sole()->origin)->toBe(FinancialTransactionOrigin::Tithe);
});

it('selects a church for the global administrator leaving the financial overview', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    User::factory()->globalAdministrator()->create();

    $this->actingAs(User::query()->sole())
        ->withSession(['active_church_id' => '__overview__'])
        ->get(route('finance.tithes.index'))
        ->assertOk()
        ->assertSessionHas('active_church_id', $church->id);
});
