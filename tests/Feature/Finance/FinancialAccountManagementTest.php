<?php

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\User;
use App\PermissionLevel;
use App\Services\AuditService;
use App\Services\Finance\FinancialAccountService;
use App\Services\PermissionService;
use App\Status;

it('allows the global administrator and denies a user without financial permission', function () {
    $area = Area::factory()->create();
    FinancialAccount::factory()->for($area)->create(['name' => 'Caixa da área']);
    $administrator = User::factory()->globalAdministrator()->create();
    $unauthorized = userWithPermission('dashboard');

    $this->actingAs($administrator)->get(route('finance.accounts.index'))->assertOk()->assertSee('Caixa da área');
    $this->actingAs($unauthorized)->get(route('finance.accounts.index'))->assertForbidden();
});

it('redirects to area setup instead of throwing an error when no area exists', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->get(route('finance.accounts.create'))
        ->assertRedirect(route('organization.index', ['panel' => 'create-area']))
        ->assertSessionHasErrors('area');
});

it('allows reading accounts but denies write operations to a reader', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $reader = userWithPermission('finance.accounts', PermissionLevel::Read, $church);
    $account = FinancialAccount::factory()->forChurch($church)->create();

    $this->actingAs($reader)->get(route('finance.accounts.index'))->assertSee($account->name);
    $this->actingAs($reader)->get(route('finance.accounts.create'))->assertForbidden();
    $this->actingAs($reader)->post(route('finance.accounts.store'), [])->assertForbidden();
    $this->actingAs($reader)->get(route('finance.accounts.edit', $account))->assertForbidden();
});

it('creates and edits a church account with write permission', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $writer = userWithPermission('finance.accounts', PermissionLevel::Write, $church);

    $this->actingAs($writer)->post(route('finance.accounts.store'), [
        'owner_type' => 'church',
        'area_id' => '',
        'church_id' => $church->id,
        'name' => '  PIX   da igreja  ',
        'type' => 'digital_wallet',
        'institution' => '  Banco Genesis  ',
        'description' => 'Recebimentos digitais',
        'status' => 'active',
    ])->assertRedirect(route('finance.accounts.index'));

    $account = FinancialAccount::query()->sole();
    expect($account->name)->toBe('PIX da igreja')
        ->and($account->area_id)->toBeNull()
        ->and($account->church_id)->toBe($church->id)
        ->and($account->institution)->toBe('Banco Genesis');

    $this->actingAs($writer)->put(route('finance.accounts.update', $account), [
        'owner_type' => 'church',
        'area_id' => '',
        'church_id' => $church->id,
        'name' => 'Conta corrente principal',
        'type' => 'checking',
        'institution' => 'Banco Genesis',
        'description' => null,
        'status' => 'active',
    ])->assertRedirect(route('finance.accounts.index'));

    expect($account->refresh()->name)->toBe('Conta corrente principal')
        ->and($account->type->value)->toBe('checking')
        ->and(AuditLog::query()->where('record_id', $account->id)->pluck('action')->all())
        ->toContain('financial_account.created', 'financial_account.updated');
});

it('creates an area account only for the global administrator', function () {
    $area = Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->post(route('finance.accounts.store'), [
        'owner_type' => 'area',
        'area_id' => $area->id,
        'church_id' => '',
        'name' => 'Caixa da área',
        'type' => 'cash',
        'institution' => null,
        'description' => null,
        'status' => 'active',
    ])->assertRedirect(route('finance.accounts.index'));

    expect(FinancialAccount::query()->sole()->area_id)->toBe($area->id);
});

it('prevents a local writer from creating an area account', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $writer = userWithPermission('finance.accounts', PermissionLevel::Write, $church);

    $this->actingAs($writer)->post(route('finance.accounts.store'), [
        'owner_type' => 'area',
        'area_id' => $area->id,
        'church_id' => '',
        'name' => 'Caixa indevido',
        'type' => 'cash',
        'institution' => null,
        'description' => null,
        'status' => 'active',
    ])->assertSessionHasErrors('owner_type');

    expect(FinancialAccount::query()->count())->toBe(0);
});

it('rejects simultaneous or missing account owners', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $administrator = User::factory()->globalAdministrator()->create();
    $base = [
        'name' => 'Conta inválida',
        'type' => 'cash',
        'institution' => null,
        'description' => null,
        'status' => 'active',
    ];

    $this->actingAs($administrator)->post(route('finance.accounts.store'), $base + [
        'owner_type' => 'area', 'area_id' => $area->id, 'church_id' => $church->id,
    ])->assertSessionHasErrors('church_id');

    $this->actingAs($administrator)->post(route('finance.accounts.store'), $base + [
        'owner_type' => 'church', 'area_id' => '', 'church_id' => '',
    ])->assertSessionHasErrors('church_id');

    expect(FinancialAccount::query()->count())->toBe(0);
});

it('enforces case-insensitive account uniqueness per owner and permits equal names for different owners', function () {
    $area = Area::factory()->create();
    $firstChurch = Church::factory()->for($area)->create();
    $secondChurch = Church::factory()->for($area)->create();
    $administrator = User::factory()->globalAdministrator()->create();
    FinancialAccount::factory()->forChurch($firstChurch)->create(['name' => 'Conta principal']);
    $payload = [
        'owner_type' => 'church',
        'area_id' => '',
        'name' => '  conta PRINCIPAL ',
        'type' => 'checking',
        'institution' => null,
        'description' => null,
        'status' => 'active',
    ];

    $this->actingAs($administrator)->post(route('finance.accounts.store'), $payload + ['church_id' => $firstChurch->id])
        ->assertSessionHasErrors('name');
    $this->actingAs($administrator)->post(route('finance.accounts.store'), $payload + ['church_id' => $secondChurch->id])
        ->assertRedirect(route('finance.accounts.index'));

    expect(FinancialAccount::query()->count())->toBe(2);
});

it('rejects invalid account types', function () {
    $area = Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->post(route('finance.accounts.store'), [
        'owner_type' => 'area',
        'area_id' => $area->id,
        'church_id' => '',
        'name' => 'Conta desconhecida',
        'type' => 'investment',
        'status' => 'active',
    ])->assertSessionHasErrors('type');

    expect(FinancialAccount::query()->count())->toBe(0);
});

it('inactivates an account without deleting it and records the audit trail', function () {
    $area = Area::factory()->create();
    $account = FinancialAccount::factory()->for($area)->create(['name' => 'Caixa histórico']);
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->patch(route('finance.accounts.inactivate', $account))->assertRedirect();

    expect($account->refresh()->status)->toBe(Status::Inactive)
        ->and(FinancialAccount::query()->whereKey($account->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'financial_account.status_changed')->where('record_id', $account->id)->exists())->toBeTrue();
    $this->actingAs($administrator)->get(route('finance.accounts.index'))->assertSee('Caixa histórico');
    $this->actingAs($administrator)->patch(route('finance.accounts.activate', $account))->assertRedirect();
    expect($account->refresh()->status)->toBe(Status::Active);
});

it('isolates church accounts and does not expose area accounts to local users', function () {
    $area = Area::factory()->create();
    $firstChurch = Church::factory()->for($area)->create();
    $secondChurch = Church::factory()->for($area)->create();
    $reader = userWithPermission('finance.accounts', PermissionLevel::Read, $firstChurch);
    $visible = FinancialAccount::factory()->forChurch($firstChurch)->create(['name' => 'Conta visível']);
    $hiddenChurch = FinancialAccount::factory()->forChurch($secondChurch)->create(['name' => 'Conta de outra igreja']);
    $hiddenArea = FinancialAccount::factory()->for($area)->create(['name' => 'Conta da área']);

    $this->actingAs($reader)->get(route('finance.accounts.index'))
        ->assertSee($visible->name)
        ->assertDontSee($hiddenChurch->name)
        ->assertDontSee($hiddenArea->name);
    $this->actingAs($reader)->get(route('finance.accounts.edit', $hiddenChurch))->assertForbidden();
});

it('rolls back account creation when auditing fails', function () {
    $area = Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();
    $audit = Mockery::mock(AuditService::class);
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
    $service = new FinancialAccountService($audit, app(PermissionService::class));

    expect(fn () => $service->create([
        'owner_type' => 'area',
        'area_id' => $area->id,
        'church_id' => null,
        'name' => 'Conta transitória',
        'type' => 'cash',
        'institution' => null,
        'description' => null,
        'status' => 'active',
    ], $administrator))->toThrow(RuntimeException::class);

    expect(FinancialAccount::query()->count())->toBe(0);
});
