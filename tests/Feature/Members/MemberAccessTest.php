<?php

use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

it('redirects visitors and forbids eligible users without members permission', function () {
    $this->get(route('members.index'))->assertRedirect(route('login'));
    $user = userWithPermission('dashboard');

    $this->actingAs($user)->get(route('members.index'))->assertForbidden();
});

it('allows readers in their church while protecting write routes', function () {
    $reader = userWithPermission('members', PermissionLevel::Read);
    $church = app(PermissionService::class)->currentChurch($reader);
    $member = Member::factory()->create();
    MemberChurchMembership::factory()->for($member)->for($church)->create();

    $this->actingAs($reader)->get(route('members.index'))->assertOk();
    $this->actingAs($reader)->get(route('members.show', $member))->assertOk();
    $this->actingAs($reader)->get(route('members.create'))->assertForbidden();
    $this->actingAs($reader)->put(route('members.update', $member), [])->assertForbidden();
    $this->actingAs($reader)->post(route('members.memberships.store', $member), [])->assertForbidden();
});

it('combines write permission with church isolation and preserves global bypass', function () {
    $writer = userWithPermission('members', PermissionLevel::Write);
    $church = app(PermissionService::class)->currentChurch($writer);
    $visible = Member::factory()->create();
    MemberChurchMembership::factory()->for($visible)->for($church)->create();
    $hidden = Member::factory()->create();
    $administrator = User::factory()->globalAdministrator()->create();

    expect($writer->can('view', $visible))->toBeTrue()->and($writer->can('view', $hidden))->toBeFalse()->and($writer->can('create', Member::class))->toBeTrue();
    expect($administrator->can('view', $hidden))->toBeTrue()->and($administrator->can('manageMemberships', $hidden))->toBeTrue();
});
