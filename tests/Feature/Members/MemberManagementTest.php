<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\Member;
use App\Models\State;
use App\Models\User;
use App\Services\MemberMembershipService;
use App\Services\MemberService;
use App\Status;
use Mockery\MockInterface;

function validMemberPayload(City $city, Church $church, array $overrides = []): array
{
    return array_replace([
        'name' => '  Maria   da Silva  ',
        'cpf' => '529.982.247-25',
        'email' => ' MARIA@EXAMPLE.COM ',
        'phone' => '(11) 98765-4321',
        'birth_date' => '1990-04-12',
        'sex' => 'female',
        'postal_code' => '01310-100',
        'state_id' => $city->state_id,
        'city_id' => $city->id,
        'street' => 'Avenida Paulista',
        'neighborhood' => 'Bela Vista',
        'number' => '100',
        'complement' => null,
        'church_id' => $church->id,
        'joined_at' => today()->toDateString(),
        'is_primary' => '1',
    ], $overrides);
}

function memberScenario(): array
{
    $area = Area::factory()->create();
    $state = State::factory()->create();
    $city = City::factory()->for($state)->create();
    $church = Church::factory()->for($area)->for($city)->create();

    return [$city, $church];
}

it('creates an active member and its initial active primary membership without creating a user', function () {
    [$city, $church] = memberScenario();
    $administrator = User::factory()->globalAdministrator()->create();

    $response = $this->actingAs($administrator)->post(route('members.store'), validMemberPayload($city, $church));

    $member = Member::query()->where('cpf', '52998224725')->firstOrFail();
    $response->assertRedirect(route('members.show', $member));
    expect($member->name)->toBe('Maria da Silva')
        ->and($member->email)->toBe('maria@example.com')
        ->and($member->phone)->toBe('11987654321')
        ->and($member->postal_code)->toBe('01310100')
        ->and($member->status)->toBe(Status::Active)
        ->and($member->user)->toBeNull();
    $this->assertDatabaseHas('member_church_memberships', [
        'member_id' => $member->id,
        'church_id' => $church->id,
        'status' => 'active',
        'is_primary' => true,
        'ended_at' => null,
    ]);
});

it('rejects invalid and duplicate CPFs and future dates', function () {
    [$city, $church] = memberScenario();
    Member::factory()->create(['cpf' => '52998224725']);
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)
        ->post(route('members.store'), validMemberPayload($city, $church, [
            'cpf' => '111.111.111-11',
            'birth_date' => today()->addDay()->toDateString(),
            'joined_at' => today()->addDay()->toDateString(),
        ]))
        ->assertSessionHasErrors(['cpf', 'birth_date', 'joined_at']);

    $this->actingAs($administrator)
        ->post(route('members.store'), validMemberPayload($city, $church))
        ->assertSessionHasErrors('cpf');
});

it('requires an active church and a city that belongs to the selected state', function () {
    [$city, $church] = memberScenario();
    $otherState = State::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)
        ->post(route('members.store'), validMemberPayload($city, $church, [
            'church_id' => null,
            'state_id' => $otherState->id,
        ]))
        ->assertSessionHasErrors(['church_id', 'city_id']);

    $church->update(['status' => Status::Inactive]);
    $this->actingAs($administrator)
        ->post(route('members.store'), validMemberPayload($city, $church))
        ->assertSessionHasErrors('church_id');
    expect(Member::query()->where('cpf', '52998224725')->exists())->toBeFalse();
});

it('guides the administrator when no active church exists', function () {
    Area::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)
        ->get(route('members.index'))
        ->assertOk()
        ->assertSee('Cadastre uma igreja ativa primeiro.')
        ->assertDontSee('href="'.route('members.create').'"', false);

    $this->actingAs($administrator)
        ->get(route('members.create'))
        ->assertOk()
        ->assertSee('Nenhuma igreja ativa disponível');
});

it('updates personal data without accepting church or status changes', function () {
    [$city, $church] = memberScenario();
    $member = Member::factory()->for($city)->create();
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->put(route('members.update', $member), validMemberPayload($city, $church, [
        'name' => 'Nome atualizado',
        'cpf' => $member->cpf,
        'status' => 'inactive',
        'church_id' => $church->id,
    ]))->assertSessionHasErrors(['status', 'church_id']);

    expect($member->refresh()->name)->not->toBe('Nome atualizado');
});

it('updates normalized personal data while preserving memberships', function () {
    [$city, $church] = memberScenario();
    $member = Member::factory()->for($city)->create();
    $membership = $member->memberships()->create([
        'church_id' => $church->id,
        'status' => Status::Active,
        'is_primary' => true,
        'joined_at' => today(),
    ]);
    $administrator = User::factory()->globalAdministrator()->create();
    $payload = validMemberPayload($city, $church, [
        'name' => '  Ana   Souza ',
        'cpf' => $member->cpf,
    ]);
    unset($payload['church_id'], $payload['joined_at'], $payload['is_primary']);

    $this->actingAs($administrator)
        ->put(route('members.update', $member), $payload)
        ->assertRedirect(route('members.show', $member));

    expect($member->refresh()->name)->toBe('Ana Souza')
        ->and($member->email)->toBe('maria@example.com')
        ->and($member->memberships()->value('id'))->toBe($membership->id);
});

it('inactivates the member and all active memberships without changing its user', function () {
    [$city, $church] = memberScenario();
    $member = Member::factory()->for($city)->create();
    $membership = $member->memberships()->create([
        'church_id' => $church->id,
        'status' => Status::Active,
        'is_primary' => true,
        'joined_at' => today()->subYear(),
    ]);
    $linkedUser = User::factory()->create(['member_id' => $member->id]);
    $administrator = User::factory()->globalAdministrator()->create();

    $this->actingAs($administrator)->patch(route('members.inactivate', $member))->assertRedirect(route('members.show', $member));

    expect($member->refresh()->status)->toBe(Status::Inactive)
        ->and($membership->refresh()->status)->toBe(Status::Inactive)
        ->and($membership->is_primary)->toBeFalse()
        ->and($membership->ended_at->isToday())->toBeTrue()
        ->and($linkedUser->refresh()->status)->toBe(Status::Active);
});

it('rolls back member creation when the initial membership fails', function () {
    [$city, $church] = memberScenario();
    $membershipService = $this->mock(MemberMembershipService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('createInitial')->once()->andThrow(new RuntimeException('failure'));
    });
    $service = new MemberService($membershipService);
    $attributes = validMemberPayload($city, $church);
    unset($attributes['state_id'], $attributes['church_id'], $attributes['joined_at'], $attributes['is_primary']);

    expect(fn () => $service->create($attributes, $church, today()->toDateString()))
        ->toThrow(RuntimeException::class);
    expect(Member::query()->where('cpf', '52998224725')->exists())->toBeFalse();
});
