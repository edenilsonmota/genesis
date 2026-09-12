<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use App\Services\ChurchService;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

function validChurchData(City $city, array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Igreja Central',
        'postal_code' => '01310-100',
        'state_id' => $city->state_id,
        'city_id' => $city->id,
        'street' => 'Avenida Principal',
        'neighborhood' => 'Centro',
        'number' => 'S/N',
        'complement' => null,
        'status' => 'active',
    ], $overrides);
}

it('creates a church linked automatically to the singleton area and normalizes its postal code', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(
        route('organization.churches.store'),
        validChurchData($city),
    );

    $response
        ->assertRedirect(route('organization.index'))
        ->assertSessionHas('success', 'Igreja cadastrada com sucesso.');
    $this->assertDatabaseHas('churches', [
        'area_id' => $area->id,
        'city_id' => $city->id,
        'name' => 'Igreja Central',
        'postal_code' => '01310100',
    ]);
});

it('does not create a church before the area exists', function () {
    $city = City::factory()->for(State::factory())->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(
        route('organization.churches.store'),
        validChurchData($city),
    );

    $response->assertSessionHasErrors([
        'area' => 'Cadastre a área antes de adicionar uma igreja.',
    ]);
    expect(Church::query()->count())->toBe(0);
});

it('does not create a church without an area when the service is called directly', function () {
    $city = City::factory()->for(State::factory())->create();
    $service = app(ChurchService::class);
    $attributes = validChurchData($city);
    unset($attributes['state_id']);

    expect(fn () => $service->create($attributes))
        ->toThrow(ValidationException::class);
});

it('validates all required church fields', function () {
    Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(route('organization.churches.store'), []);

    $response->assertSessionHasErrors([
        'name',
        'postal_code',
        'state_id',
        'city_id',
        'street',
        'neighborhood',
        'number',
        'status',
    ]);
});

it('rejects a city that does not belong to the selected state', function () {
    Area::factory()->create();
    $selectedState = State::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(
        route('organization.churches.store'),
        validChurchData($city, ['state_id' => $selectedState->id]),
    );

    $response->assertSessionHasErrors([
        'city_id' => 'A cidade selecionada não pertence ao estado informado.',
    ]);
    expect(Church::query()->count())->toBe(0);
});

it('rejects a client supplied area identifier', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(
        route('organization.churches.store'),
        validChurchData($city, ['area_id' => $area->id]),
    );

    $response->assertSessionHasErrors('area_id');
    expect(Church::query()->count())->toBe(0);
});

it('rejects duplicate church names without differentiating letter case', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    Church::factory()->for($area)->for($city)->create(['name' => 'Igreja Central']);
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(
        route('organization.churches.store'),
        validChurchData($city, ['name' => 'IGREJA CENTRAL']),
    );

    $response->assertSessionHasErrors([
        'name' => 'Já existe uma igreja com este nome na área.',
    ]);
    expect(Church::query()->count())->toBe(1);
});

it('blocks duplicate church names directly in PostgreSQL', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    Church::factory()->for($area)->for($city)->create(['name' => 'Igreja Central']);

    expect(fn () => Church::factory()->for($area)->for($city)->create(['name' => 'IGREJA CENTRAL']))
        ->toThrow(QueryException::class);
});

it('updates a church without changing its area', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    $newCity = City::factory()->for($city->state)->create();
    $church = Church::factory()->for($area)->for($city)->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->put(
        route('organization.churches.update', $church),
        validChurchData($newCity, ['name' => 'Igreja Renovada']),
    );

    $response->assertRedirect(route('organization.index'));
    $this->assertDatabaseHas('churches', [
        'id' => $church->id,
        'area_id' => $area->id,
        'city_id' => $newCity->id,
        'name' => 'Igreja Renovada',
    ]);
});

it('returns only cities belonging to the requested state', function () {
    $firstState = State::factory()->create();
    $secondState = State::factory()->create();
    $firstCity = City::factory()->for($firstState)->create(['name' => 'Cidade esperada']);
    City::factory()->for($secondState)->create(['name' => 'Outra cidade']);
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->get(route('organization.cities.index', $firstState));

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', $firstCity->id)
        ->assertJsonPath('data.0.name', 'Cidade esperada')
        ->assertJsonCount(1, 'data');
});

it('rejects malformed postal codes and unsupported statuses', function () {
    Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(
        route('organization.churches.store'),
        validChurchData($city, ['postal_code' => '123', 'status' => 'transferred']),
    );

    $response->assertSessionHasErrors(['postal_code', 'status']);
    expect(Church::query()->count())->toBe(0);
});

it('inactivates a church without deleting its history', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    $church = Church::factory()->for($area)->for($city)->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->patch(
        route('organization.churches.inactivate', $church),
    );

    $response->assertRedirect(route('organization.index'));
    expect($church->refresh()->status)->toBe(Status::Inactive);
    $this->assertModelExists($church);
});
