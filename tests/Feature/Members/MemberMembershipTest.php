<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\State;
use App\Models\User;
use App\Status;

function membershipSetup(): array
{
    $area = Area::factory()->create();
    $city = City::factory()->for(State::factory())->create();
    $churches = Church::factory()->count(3)->for($area)->for($city)->create();
    $member = Member::factory()->for($city)->create();
    $primary = MemberChurchMembership::factory()->for($member)->for($churches[0])->create(['is_primary' => true]);

    return [$member, $churches, $primary, User::factory()->globalAdministrator()->create()];
}

it('adds an active secondary church and prevents duplicate active memberships', function () {
    [$member, $churches, $primary, $administrator] = membershipSetup();

    $this->actingAs($administrator)->post(route('members.memberships.store', $member), [
        'church_id' => $churches[1]->id,
        'joined_at' => today()->toDateString(),
    ])->assertRedirect();

    $secondary = $member->memberships()->where('church_id', $churches[1]->id)->firstOrFail();
    expect($secondary->status)->toBe(Status::Active)
        ->and($secondary->is_primary)->toBeFalse()
        ->and($primary->refresh()->is_primary)->toBeTrue();

    $this->actingAs($administrator)->post(route('members.memberships.store', $member), [
        'church_id' => $churches[1]->id,
        'joined_at' => today()->toDateString(),
    ])->assertSessionHasErrors('church_id');
});

it('switches the primary church before allowing the old primary to end', function () {
    [$member, $churches, $primary, $administrator] = membershipSetup();
    $secondary = MemberChurchMembership::factory()->for($member)->for($churches[1])->create();

    $this->actingAs($administrator)
        ->patch(route('members.memberships.end', [$member, $primary]))
        ->assertSessionHasErrors('membership');

    $this->actingAs($administrator)
        ->patch(route('members.memberships.primary', [$member, $secondary]))
        ->assertSessionHas('success');
    expect($secondary->refresh()->is_primary)->toBeTrue()
        ->and($primary->refresh()->is_primary)->toBeFalse();

    $this->actingAs($administrator)
        ->patch(route('members.memberships.end', [$member, $primary]))
        ->assertSessionHas('success');
    expect($primary->refresh()->status)->toBe(Status::Inactive)
        ->and($primary->ended_at->isToday())->toBeTrue();
});

it('does not allow ending the only active membership or adding churches to an inactive member', function () {
    [$member, $churches, $primary, $administrator] = membershipSetup();

    $this->actingAs($administrator)
        ->patch(route('members.memberships.end', [$member, $primary]))
        ->assertSessionHasErrors('membership');

    $member->update(['status' => Status::Inactive]);
    $this->actingAs($administrator)->post(route('members.memberships.store', $member), [
        'church_id' => $churches[1]->id,
        'joined_at' => today()->toDateString(),
    ])->assertSessionHasErrors('membership');
});

it('rejects a membership that belongs to another member', function () {
    [$member, $churches, $primary, $administrator] = membershipSetup();
    $other = Member::factory()->create();

    $this->actingAs($administrator)
        ->patch(route('members.memberships.primary', [$other, $primary]))
        ->assertNotFound();
});
