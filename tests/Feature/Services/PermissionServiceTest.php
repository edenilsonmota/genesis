<?php

use App\Models\Area;
use App\Models\Church;
use App\Models\Department;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberPositionAssignment;
use App\Models\PermissionModule;
use App\Models\Position;
use App\Models\PositionPermission;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

it('combines several positions using the highest permission level per church', function () {
    $user = User::factory()->create();
    $church = grantPermissionToUser($user, 'members', PermissionLevel::Read);
    grantPermissionToUser($user, 'members', PermissionLevel::Write, $church);
    $service = app(PermissionService::class);

    expect($service->can($user->refresh(), 'members', PermissionLevel::Write, $church))->toBeTrue()
        ->and($service->can($user, 'members', PermissionLevel::Read, $church))->toBeTrue()
        ->and($service->effectivePermissions($user, $church)['members'])->toBe(PermissionLevel::Write);
});

it('does not transfer permissions between churches', function () {
    $user = User::factory()->create();
    $churchA = grantPermissionToUser($user, 'members', PermissionLevel::Write);
    $churchB = Church::factory()->for($churchA->area)->create();
    $member = Member::query()->findOrFail($user->member_id);
    MemberChurchMembership::factory()->for($member)->for($churchB)->create();

    expect(app(PermissionService::class)->can($user->refresh(), 'members', PermissionLevel::Read, $churchB))->toBeFalse();
});

it('ignores department status when calculating authorization', function () {
    $area = Area::factory()->create();
    $church = Church::factory()->for($area)->create();
    $member = Member::factory()->create();
    $user = User::factory()->for($member)->create();
    $membership = MemberChurchMembership::factory()->for($member)->for($church)->create();
    $department = Department::factory()->for($area)->create(['status' => 'inactive']);
    $position = Position::factory()->for($area)->for($department)->grantingAccess()->create();
    $module = PermissionModule::factory()->create(['key' => 'members']);
    PositionPermission::factory()->for($position)->for($module, 'permissionModule')->create(['level' => 'read']);
    MemberPositionAssignment::factory()->for($membership, 'membership')->for($position)->create();

    expect(app(PermissionService::class)->can($user, 'members', PermissionLevel::Read, $church))->toBeTrue();
});

it('revokes future expired inactive and non-access positions immediately', function () {
    $user = User::factory()->create();
    $church = grantPermissionToUser($user, 'members');
    $assignment = MemberPositionAssignment::query()->sole();
    $service = app(PermissionService::class);

    $assignment->update(['started_at' => today()->addDay()]);
    expect($service->can($user, 'members', PermissionLevel::Read, $church))->toBeFalse();
    $assignment->update(['started_at' => today()->subDays(2), 'ended_at' => today()->subDay()]);
    expect($service->can($user, 'members', PermissionLevel::Read, $church))->toBeFalse();
    $assignment->update(['ended_at' => null, 'status' => 'inactive']);
    expect($service->can($user, 'members', PermissionLevel::Read, $church))->toBeFalse();
    $assignment->update(['status' => 'active']);
    $assignment->position->update(['status' => 'inactive']);
    expect($service->can($user, 'members', PermissionLevel::Read, $church))->toBeFalse();
    $assignment->position->update(['status' => 'active']);
    $assignment->position->update(['grants_system_access' => false]);
    expect($service->can($user, 'members', PermissionLevel::Read, $church))->toBeFalse();
});

it('gives an active global administrator a guarded bypass without member or position', function () {
    $administrator = User::factory()->globalAdministrator()->create();

    expect(app(PermissionService::class)->can($administrator, 'unknown', PermissionLevel::Write))->toBeTrue()
        ->and($administrator->member_id)->toBeNull();

    $administrator->update(['is_global_administrator' => false]);
    expect($administrator->refresh()->is_global_administrator)->toBeTrue();
});
