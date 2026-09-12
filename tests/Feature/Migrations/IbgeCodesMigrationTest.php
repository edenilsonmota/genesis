<?php

use App\Models\City;
use App\Models\State;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('rolls back and reapplies unique IBGE codes for states and cities', function () {
    $migration = require database_path('migrations/2026_09_12_021813_add_ibge_codes_to_states_and_cities_tables.php');

    $migration->down();

    expect(Schema::hasColumn('states', 'ibge_code'))->toBeFalse()
        ->and(Schema::hasColumn('cities', 'ibge_code'))->toBeFalse();

    $migration->up();

    expect(Schema::hasColumn('states', 'ibge_code'))->toBeTrue()
        ->and(Schema::hasColumn('cities', 'ibge_code'))->toBeTrue()
        ->and(DB::table('pg_indexes')->where('indexname', 'states_ibge_code_unique')->exists())->toBeTrue()
        ->and(DB::table('pg_indexes')->where('indexname', 'cities_ibge_code_unique')->exists())->toBeTrue();
});

it('rejects duplicate IBGE codes for states', function () {
    State::factory()->create(['ibge_code' => 35]);

    expect(fn () => State::factory()->create(['ibge_code' => 35]))
        ->toThrow(QueryException::class);
});

it('rejects duplicate IBGE codes for cities', function () {
    $state = State::factory()->create();
    City::factory()->for($state)->create(['ibge_code' => 3550308]);

    expect(fn () => City::factory()->for($state)->create(['ibge_code' => 3550308]))
        ->toThrow(QueryException::class);
});
