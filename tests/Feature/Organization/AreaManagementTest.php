<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use App\Services\AreaService;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

it('creates the first and only area through the interface', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(route('organization.area.store'), [
        'name' => '  Área Central  ',
        'description' => 'Organização principal',
        'status' => 'active',
    ]);

    $response
        ->assertRedirect(route('organization.index'))
        ->assertSessionHas('success', 'Área cadastrada com sucesso.');
    $this->assertDatabaseHas('areas', [
        'name' => 'Área Central',
        'description' => 'Organização principal',
        'status' => 'active',
    ]);
});

it('rejects a second area through the form request', function () {
    Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(route('organization.area.store'), [
        'name' => 'Outra área',
        'description' => null,
        'status' => 'active',
    ]);

    $response->assertSessionHasErrors([
        'name' => 'Esta instalação já possui uma área cadastrada.',
    ]);
    expect(Area::query()->count())->toBe(1);
});

it('rejects a second area in the service', function () {
    Area::factory()->create();
    $service = app(AreaService::class);

    expect(fn () => $service->create([
        'name' => 'Outra área',
        'description' => null,
        'status' => 'active',
    ]))->toThrow(ValidationException::class);
});

it('blocks a second area directly in PostgreSQL', function () {
    Area::factory()->create(['name' => 'Área existente']);

    expect(fn () => Area::factory()->create(['name' => 'Área concorrente']))
        ->toThrow(QueryException::class);
});

it('updates the existing area', function () {
    $area = Area::factory()->create(['name' => 'Área antiga']);
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->put(route('organization.area.update', $area), [
        'name' => 'Área atualizada',
        'description' => 'Nova descrição',
        'status' => 'active',
    ]);

    $response->assertRedirect(route('organization.index'));
    $this->assertDatabaseHas('areas', [
        'id' => $area->id,
        'name' => 'Área atualizada',
        'description' => 'Nova descrição',
    ]);
});

it('does not inactivate an area while it has active churches', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    Church::factory()->for($area)->for($city)->create(['status' => Status::Active]);
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->put(route('organization.area.update', $area), [
        'name' => $area->name,
        'description' => $area->description,
        'status' => 'inactive',
    ]);

    $response->assertSessionHasErrors([
        'status' => 'A área não pode ser inativada enquanto possuir igrejas ativas.',
    ]);
    expect($area->refresh()->status)->toBe(Status::Active);
});
