<?php

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\FinancialCategory;
use App\Models\User;
use App\PermissionLevel;
use App\Status;

it('redirects to area setup instead of throwing an error when no area exists', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->get(route('finance.categories.create'))
        ->assertRedirect(route('organization.index', ['panel' => 'create-area']))
        ->assertSessionHasErrors('area');
});

it('enforces category read and write permissions', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $reader = userWithPermission('finance.categories', PermissionLevel::Read, $church);
    $category = FinancialCategory::factory()->for($area)->create(['name' => 'Ofertas']);

    $this->actingAs($reader)->get(route('finance.categories.index'))->assertSee('Ofertas');
    $this->actingAs($reader)->get(route('finance.categories.create'))->assertForbidden();
    $this->actingAs($reader)->post(route('finance.categories.store'), [])->assertForbidden();
    $this->actingAs($reader)->get(route('finance.categories.edit', $category))->assertForbidden();
});

it('creates and edits a category with write permission', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $writer = userWithPermission('finance.categories', PermissionLevel::Write, $church);

    $this->actingAs($writer)->post(route('finance.categories.store'), [
        'area_id' => $area->id,
        'name' => '  Ação   social ',
        'type' => 'expense',
        'description' => 'Projetos sociais',
        'status' => 'active',
        'fixed' => '1',
    ])->assertRedirect(route('finance.categories.index'));

    $category = FinancialCategory::query()->sole();
    expect($category->name)->toBe('Ação social')->and($category->fixed)->toBeFalse();

    $this->actingAs($writer)->put(route('finance.categories.update', $category), [
        'area_id' => $area->id,
        'name' => 'Assistência social',
        'type' => 'expense',
        'description' => null,
        'status' => 'active',
    ])->assertRedirect(route('finance.categories.index'));

    expect($category->refresh()->name)->toBe('Assistência social');
});

it('requires the active area and accepts only income or expense types', function () {
    $area = Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();
    $payload = ['name' => 'Dízimos', 'description' => null, 'status' => 'active'];

    $this->actingAs($administrator)->post(route('finance.categories.store'), $payload + [
        'area_id' => '', 'type' => 'income',
    ])->assertSessionHasErrors('area_id');
    $this->actingAs($administrator)->post(route('finance.categories.store'), $payload + [
        'area_id' => $area->id, 'type' => 'transfer',
    ])->assertSessionHasErrors('type');

    expect(FinancialCategory::query()->count())->toBe(0);
});

it('enforces case-insensitive category uniqueness by area and type', function () {
    $area = Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();
    FinancialCategory::factory()->for($area)->create(['name' => 'Doações', 'type' => 'income']);
    $payload = [
        'area_id' => $area->id,
        'name' => '  DOAÇÕES  ',
        'description' => null,
        'status' => 'active',
    ];

    $this->actingAs($administrator)->post(route('finance.categories.store'), $payload + ['type' => 'income'])
        ->assertSessionHasErrors('name');
    $this->actingAs($administrator)->post(route('finance.categories.store'), $payload + ['type' => 'expense'])
        ->assertRedirect(route('finance.categories.index'));

    expect(FinancialCategory::query()->count())->toBe(2);
});

it('protects fixed categories from editing and status changes', function () {
    $area = Area::factory()->create();
    $category = FinancialCategory::factory()->for($area)->fixed()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->get(route('finance.categories.edit', $category))->assertForbidden();
    $this->actingAs($administrator)->put(route('finance.categories.update', $category), [
        'area_id' => $area->id,
        'name' => 'Alterada',
        'type' => 'income',
        'status' => 'active',
    ])->assertForbidden();
    $this->actingAs($administrator)->patch(route('finance.categories.inactivate', $category))->assertForbidden();
    expect($category->refresh()->status)->toBe(Status::Active);
});

it('inactivates a category without deleting it and records the audit trail', function () {
    $area = Area::factory()->create();
    $category = FinancialCategory::factory()->for($area)->create(['name' => 'Campanhas']);
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->patch(route('finance.categories.inactivate', $category))->assertRedirect();

    expect($category->refresh()->status)->toBe(Status::Inactive)
        ->and(FinancialCategory::query()->whereKey($category->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'financial_category.status_changed')->where('record_id', $category->id)->exists())->toBeTrue();

    $this->actingAs($administrator)->patch(route('finance.categories.activate', $category))->assertRedirect();
    expect($category->refresh()->status)->toBe(Status::Active);
});

it('records create and update audits with the area scope', function () {
    $area = Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();
    $payload = [
        'area_id' => $area->id,
        'name' => 'Manutenção',
        'type' => 'expense',
        'description' => null,
        'status' => 'active',
    ];

    $this->actingAs($administrator)->post(route('finance.categories.store'), $payload)->assertRedirect();
    $category = FinancialCategory::query()->sole();
    $this->actingAs($administrator)->put(route('finance.categories.update', $category), [...$payload, 'name' => 'Manutenção predial'])->assertRedirect();

    expect(AuditLog::query()->where('record_id', $category->id)->where('scope_type', 'area')->where('scope_id', $area->id)->pluck('action')->all())
        ->toContain('financial_category.created', 'financial_category.updated');
});
