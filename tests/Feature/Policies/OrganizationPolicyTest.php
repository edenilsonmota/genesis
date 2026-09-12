<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

it('grants organization management abilities only to global administrators', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    $church = Church::factory()->for($area)->for($city)->create();
    $administrator = User::factory()->globalAdministrator()->create();
    $regularUser = User::factory()->create();

    $administratorAbilities = [
        Gate::forUser($administrator)->allows('viewAny', Area::class),
        Gate::forUser($administrator)->allows('create', Area::class),
        Gate::forUser($administrator)->allows('update', $area),
        Gate::forUser($administrator)->allows('viewAny', Church::class),
        Gate::forUser($administrator)->allows('create', Church::class),
        Gate::forUser($administrator)->allows('update', $church),
        Gate::forUser($administrator)->allows('inactivate', $church),
    ];
    $regularUserAbilities = [
        Gate::forUser($regularUser)->allows('viewAny', Area::class),
        Gate::forUser($regularUser)->allows('create', Area::class),
        Gate::forUser($regularUser)->allows('update', $area),
        Gate::forUser($regularUser)->allows('viewAny', Church::class),
        Gate::forUser($regularUser)->allows('create', Church::class),
        Gate::forUser($regularUser)->allows('update', $church),
        Gate::forUser($regularUser)->allows('inactivate', $church),
    ];

    expect($administratorAbilities)->each->toBeTrue()
        ->and($regularUserAbilities)->each->toBeFalse()
        ->and(Gate::forUser($administrator)->allows('delete', $area))->toBeFalse()
        ->and(Gate::forUser($administrator)->allows('delete', $church))->toBeFalse();
});
