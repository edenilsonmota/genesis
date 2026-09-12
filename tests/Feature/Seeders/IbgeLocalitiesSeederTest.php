<?php

use App\Models\City;
use App\Models\State;
use Database\Seeders\IbgeLocalitiesSeeder;
use Illuminate\Support\Facades\Http;

it('seeds the versioned IBGE snapshot idempotently without network access', function () {
    Http::preventStrayRequests();

    $this->seed(IbgeLocalitiesSeeder::class);
    $this->seed(IbgeLocalitiesSeeder::class);

    expect(State::query()->count())->toBe(27)
        ->and(City::query()->count())->toBe(5_571);
    $this->assertDatabaseHas('states', [
        'ibge_code' => 35,
        'abbreviation' => 'SP',
        'name' => 'São Paulo',
    ]);
    $this->assertDatabaseHas('cities', [
        'ibge_code' => 3550308,
        'name' => 'São Paulo',
    ]);
    Http::assertNothingSent();
});

it('backfills matching legacy localities and preserves their internal identifiers', function () {
    $state = State::factory()->create([
        'ibge_code' => null,
        'abbreviation' => 'SP',
        'name' => 'Estado legado',
    ]);
    $city = City::factory()->for($state)->create([
        'ibge_code' => null,
        'name' => 'São Paulo',
    ]);
    Http::preventStrayRequests();

    $this->seed(IbgeLocalitiesSeeder::class);

    $this->assertDatabaseHas('states', [
        'id' => $state->id,
        'ibge_code' => 35,
        'abbreviation' => 'SP',
        'name' => 'São Paulo',
    ]);
    $this->assertDatabaseHas('cities', [
        'id' => $city->id,
        'state_id' => $state->id,
        'ibge_code' => 3550308,
        'name' => 'São Paulo',
    ]);
    expect(State::query()->count())->toBe(27)
        ->and(City::query()->count())->toBe(5_571);
    Http::assertNothingSent();
});
