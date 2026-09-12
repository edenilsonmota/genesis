<?php

use App\Enums\Sex;
use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\State;
use App\Models\User;
use App\Status;
use Illuminate\Support\Facades\DB;

it('searches members by name CPF email and phone', function (string $field, string $query) {
    $member = Member::factory()->create([
        'name' => 'Helena Pesquisa',
        'email' => 'helena.pesquisa@example.com',
        'phone' => '11987654321',
    ]);
    $administrator = User::factory()->globalAdministrator()->create();
    $query = $field === 'cpf' ? $member->cpf : $query;

    $this->actingAs($administrator)
        ->get(route('members.index', ['search' => $query]))
        ->assertOk()
        ->assertSee('Helena Pesquisa');
})->with([
    'name' => ['name', 'Helena Pesq'],
    'cpf' => ['cpf', ''],
    'email' => ['email', 'helena.pesquisa@'],
    'phone' => ['phone', '(11) 98765-4321'],
]);

it('filters by active church status and sex', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    [$firstChurch, $secondChurch] = Church::factory()->count(2)->for($area)->for($city)->create();
    $matching = Member::factory()->for($city)->create(['name' => 'Pessoa esperada', 'sex' => Sex::Female, 'status' => Status::Active]);
    $other = Member::factory()->for($city)->create(['name' => 'Pessoa fora do filtro', 'sex' => Sex::Male, 'status' => Status::Inactive]);
    MemberChurchMembership::factory()->for($matching)->for($firstChurch)->create(['is_primary' => true]);
    MemberChurchMembership::factory()->for($other)->for($secondChurch)->create(['is_primary' => true]);
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->get(route('members.index', [
        'church_id' => $firstChurch->id,
        'status' => Status::Active->value,
        'sex' => Sex::Female->value,
    ]))->assertOk()
        ->assertSee('Pessoa esperada')
        ->assertDontSee('Pessoa fora do filtro');
});

it('paginates the member list with a stable order', function () {
    Member::factory()->count(11)->sequence(
        fn ($sequence): array => ['name' => sprintf('Membro %02d', $sequence->index + 1)],
    )->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)
        ->get(route('members.index'))
        ->assertOk()
        ->assertSee('Membro 01')
        ->assertSee('Membro 10')
        ->assertDontSee('Membro 11');

    $this->actingAs($administrator)
        ->get(route('members.index', ['page' => 2]))
        ->assertSee('Membro 11');
});

it('loads list relationships without query growth per member', function () {
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    $church = Church::factory()->for($area)->for($city)->create();
    $members = Member::factory()->count(10)->for($city)->create();

    foreach ($members as $member) {
        MemberChurchMembership::factory()->for($member)->for($church)->create(['is_primary' => true]);
    }

    $administrator = User::factory()->globalAdministrator()->create();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $this->actingAs($administrator)->get(route('members.index'))->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThan(15);
});
