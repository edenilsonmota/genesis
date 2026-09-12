<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use App\Status;

it('searches churches by name without differentiating letter case', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    Church::factory()->for($area)->for($city)->create(['name' => 'Comunidade Esperança']);
    Church::factory()->for($area)->for($city)->create(['name' => 'Igreja do Caminho']);
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->get(route('organization.index', [
        'search' => 'ESPERANÇA',
    ]));

    $response->assertViewHas('churches', fn ($churches): bool => $churches->count() === 1 && $churches->first()->name === 'Comunidade Esperança');
});

it('filters churches by status without hiding inactive records by default', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    Church::factory()->for($area)->for($city)->create(['name' => 'Igreja Ativa', 'status' => Status::Active]);
    Church::factory()->for($area)->for($city)->create(['name' => 'Igreja Histórica', 'status' => Status::Inactive]);
    $administrator = User::factory()->globalAdministrator()->create();

    $unfilteredResponse = $this->actingAs($administrator)->get(route('organization.index'));
    $filteredResponse = $this->actingAs($administrator)->get(route('organization.index', ['status' => 'inactive']));

    $unfilteredResponse->assertViewHas('churches', fn ($churches): bool => $churches->count() === 2);
    $filteredResponse->assertViewHas('churches', fn ($churches): bool => $churches->count() === 1 && $churches->first()->name === 'Igreja Histórica');
});

it('filters churches by state and city', function () {
    $area = Area::factory()->create();
    $firstState = State::factory()->create(['abbreviation' => 'SP']);
    $secondState = State::factory()->create(['abbreviation' => 'RJ']);
    $firstCity = City::factory()->for($firstState)->create(['name' => 'São Paulo']);
    $secondCity = City::factory()->for($secondState)->create(['name' => 'Rio de Janeiro']);
    Church::factory()->for($area)->for($firstCity)->create(['name' => 'Igreja Paulista']);
    Church::factory()->for($area)->for($secondCity)->create(['name' => 'Igreja Carioca']);
    $administrator = User::factory()->globalAdministrator()->create();

    $stateResponse = $this->actingAs($administrator)->get(route('organization.index', [
        'state_id' => $firstState->id,
    ]));
    $cityResponse = $this->actingAs($administrator)->get(route('organization.index', [
        'state_id' => $secondState->id,
        'city_id' => $secondCity->id,
    ]));

    $stateResponse->assertViewHas('churches', fn ($churches): bool => $churches->count() === 1 && $churches->first()->name === 'Igreja Paulista');
    $cityResponse->assertViewHas('churches', fn ($churches): bool => $churches->count() === 1 && $churches->first()->name === 'Igreja Carioca');
});

it('paginates the church list ten records at a time', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();

    foreach (range(1, 11) as $number) {
        Church::factory()->for($area)->for($city)->create([
            'name' => 'Igreja '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $administrator = User::factory()->globalAdministrator()->create();

    $firstPage = $this->actingAs($administrator)->get(route('organization.index'));
    $secondPage = $this->actingAs($administrator)->get(route('organization.index', ['page' => 2]));

    $firstPage->assertViewHas('churches', fn ($churches): bool => $churches->count() === 10 && $churches->first()->name === 'Igreja 01')
        ->assertSee('page=2', false);
    $secondPage->assertViewHas('churches', fn ($churches): bool => $churches->count() === 1 && $churches->first()->name === 'Igreja 11');
});
