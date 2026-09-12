<?php

use App\Enums\Sex;
use App\Models\City;
use App\Models\Member;
use App\Models\State;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('casts the persisted sex value to the domain enum', function () {
    $member = Member::factory()->create(['sex' => Sex::Male]);

    expect($member->refresh()->sex)->toBe(Sex::Male)
        ->and($member->sex->label())->toBe('Masculino');
});

it('persists each valid sex value', function (Sex $sex) {
    $member = Member::factory()->create(['sex' => $sex]);

    $this->assertDatabaseHas('members', [
        'id' => $member->id,
        'sex' => $sex->value,
    ]);
})->with([
    'male' => Sex::Male,
    'female' => Sex::Female,
]);

it('rejects a sex value outside the database constraint', function () {
    $city = City::factory()->for(State::factory())->create();

    expect(fn () => DB::table('members')->insert([
        'id' => (string) Str::uuid(),
        'name' => 'Pessoa inválida',
        'cpf' => '12345678901',
        'city_id' => $city->id,
        'sex' => 'invalid',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
