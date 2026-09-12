<?php

use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberPositionAssignment;
use App\Models\Position;
use App\Models\User;

it('assigns a position to an active church membership and derives its department', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $member = Member::factory()->create();
    $membership = MemberChurchMembership::factory()->for($member)->for($church)->create(['joined_at' => today()->subMonth()]);
    $position = Position::factory()->for($area)->create();

    $this->actingAs($administrator)->post(route('members.positions.store', $member), [
        'member_church_membership_id' => $membership->id, 'position_id' => $position->id, 'started_at' => today()->toDateString(),
    ])->assertRedirect();

    $assignment = MemberPositionAssignment::query()->sole();
    expect($assignment->position_id)->toBe($position->id)->and($assignment->getAttributes())->not->toHaveKey('department_id');
    expect(AuditLog::query()->where('action', 'member_position.assigned')->exists())->toBeTrue();
});

it('rejects inactive memberships inactive positions and a membership from another member', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $member = Member::factory()->create();
    $inactiveMembership = MemberChurchMembership::factory()->for($member)->for($church)->create(['status' => 'inactive', 'ended_at' => today()]);
    $inactivePosition = Position::factory()->for($area)->create(['status' => 'inactive']);

    $this->actingAs($administrator)->post(route('members.positions.store', $member), [
        'member_church_membership_id' => $inactiveMembership->id, 'position_id' => $inactivePosition->id, 'started_at' => today()->toDateString(),
    ])->assertSessionHasErrors('member_church_membership_id');

    $other = Member::factory()->create();
    $otherMembership = MemberChurchMembership::factory()->for($other)->for($church)->create();
    $activePosition = Position::factory()->for($area)->create();
    $this->actingAs($administrator)->post(route('members.positions.store', $member), [
        'member_church_membership_id' => $otherMembership->id, 'position_id' => $activePosition->id, 'started_at' => today()->toDateString(),
    ])->assertNotFound();

    $activeMembership = MemberChurchMembership::factory()->for($member)->for($church)->create();
    $this->actingAs($administrator)->post(route('members.positions.store', $member), [
        'member_church_membership_id' => $activeMembership->id,
        'position_id' => $inactivePosition->id,
        'started_at' => today()->toDateString(),
    ])->assertSessionHasErrors('position_id');
});

it('preserves history and permits reassignment after an assignment ends', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $member = Member::factory()->create();
    $membership = MemberChurchMembership::factory()->for($member)->for($church)->create();
    $position = Position::factory()->for($area)->create();
    $assignment = MemberPositionAssignment::factory()->for($membership, 'membership')->for($position)->create(['started_at' => today()->subMonth()]);

    $this->actingAs($administrator)->patch(route('members.positions.end', [$member, $assignment]), ['ended_at' => today()->toDateString()])->assertRedirect();
    $this->actingAs($administrator)->post(route('members.positions.store', $member), [
        'member_church_membership_id' => $membership->id, 'position_id' => $position->id, 'started_at' => today()->addDay()->toDateString(),
    ])->assertRedirect();

    expect(MemberPositionAssignment::query()->count())->toBe(2)->and($assignment->refresh()->status->value)->toBe('inactive');
});

it('rejects two simultaneously active assignments of the same position', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    ['member' => $member, 'membership' => $membership, 'position' => $position] = eligibleMemberForUser();

    $this->actingAs($administrator)->post(route('members.positions.store', $member), [
        'member_church_membership_id' => $membership->id, 'position_id' => $position->id, 'started_at' => today()->toDateString(),
    ])->assertSessionHasErrors('position_id');
});

it('treats an inactive department only as categorization for an existing active position', function () {
    $administrator = User::factory()->globalAdministrator()->create();
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $member = Member::factory()->create();
    $membership = MemberChurchMembership::factory()->for($member)->for($church)->create();
    $department = Department::factory()->for($area)->create(['status' => 'inactive']);
    $position = Position::factory()->for($area)->for($department)->create();

    $this->actingAs($administrator)->post(route('members.positions.store', $member), [
        'member_church_membership_id' => $membership->id,
        'position_id' => $position->id,
        'started_at' => today()->toDateString(),
    ])->assertRedirect();

    expect(MemberPositionAssignment::query()->sole()->position->department_id)->toBe($department->id);
});
