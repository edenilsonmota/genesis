<?php

use App\Enums\FinancialAccountType;
use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\FinancialAccount;
use App\Models\User;
use App\Services\Finance\DefaultFinancialAccountService;
use App\Services\Finance\FinancialCategoryProvisioningService;
use App\Status;

it('creates the default area cash account with the area', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->post(route('organization.area.store'), [
        'name' => 'Área Central',
        'description' => null,
        'status' => Status::Active->value,
    ])->assertRedirect(route('organization.index'));

    $area = Area::query()->sole();
    $account = FinancialAccount::query()->where('area_id', $area->id)->sole();

    expect($account->name)->toBe(DefaultFinancialAccountService::AREA_ACCOUNT_NAME)
        ->and($account->type)->toBe(FinancialAccountType::Cash)
        ->and($account->is_default)->toBeTrue()
        ->and($account->status)->toBe(Status::Active);
    expect($area->financialCategories()->where('fixed', true)->count())
        ->toBe(31);
});

it('creates the default church cash account with the church', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $city = City::factory()->create();

    $this->actingAs($administrator)->post(route('organization.churches.store'), [
        'city_id' => $city->id,
        'state_id' => $city->state_id,
        'name' => 'Igreja Central',
        'postal_code' => '01001000',
        'street' => 'Rua Principal',
        'neighborhood' => 'Centro',
        'number' => '100',
        'complement' => null,
        'status' => Status::Active->value,
    ])->assertRedirect(route('organization.index'));

    $church = Church::query()->sole();
    $account = FinancialAccount::query()->where('church_id', $church->id)->sole();

    expect($account->name)->toBe(DefaultFinancialAccountService::CHURCH_ACCOUNT_NAME)
        ->and($account->type)->toBe(FinancialAccountType::Cash)
        ->and($account->is_default)->toBeTrue()
        ->and($account->status)->toBe(Status::Active);
});

it('keeps one default cash account per existing area and church', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $service = app(DefaultFinancialAccountService::class);

    $service->ensureForArea($area);
    $service->ensureForArea($area);
    $service->ensureForChurch($church);
    $service->ensureForChurch($church);

    expect(FinancialAccount::query()->where('area_id', $area->id)->where('is_default', true)->count())->toBe(1)
        ->and(FinancialAccount::query()->where('church_id', $church->id)->where('is_default', true)->count())->toBe(1);
});

it('provisions all defaults only once for an existing area', function () {
    $area = Area::factory()->create();
    $service = app(FinancialCategoryProvisioningService::class);

    $service->ensureForArea($area);
    $service->ensureForArea($area);

    expect($area->financialCategories()->where('fixed', true)->count())->toBe(31);
});
