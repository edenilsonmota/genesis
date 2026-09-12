<?php

use App\Models\Member;
use App\Models\User;
use App\PermissionLevel;

it('redirects visitors to login and forbids users without members permission', function () {
    $this->get(route('members.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('members.index'))
        ->assertForbidden();
});

it('allows readers to list and view members but protects every write route', function () {
    $reader = User::factory()->withGlobalPermission('members', PermissionLevel::Read)->create();
    $member = Member::factory()->create();

    $this->actingAs($reader)->get(route('members.index'))->assertOk();
    $this->actingAs($reader)->get(route('members.show', $member))->assertOk();
    $this->actingAs($reader)->get(route('members.create'))->assertForbidden();
    $this->actingAs($reader)->post(route('members.store'), [])->assertForbidden();
    $this->actingAs($reader)->get(route('members.edit', $member))->assertForbidden();
    $this->actingAs($reader)->put(route('members.update', $member), [])->assertForbidden();
    $this->actingAs($reader)->patch(route('members.inactivate', $member))->assertForbidden();
    $this->actingAs($reader)->post(route('members.memberships.store', $member), [])->assertForbidden();
});

it('grants read and write abilities to writers and global administrators', function () {
    $writer = User::factory()->withGlobalPermission('members', PermissionLevel::Write)->create();
    $administrator = User::factory()->globalAdministrator()->create();
    $member = Member::factory()->create();

    foreach ([$writer, $administrator] as $user) {
        expect($user->can('viewAny', Member::class))->toBeTrue()
            ->and($user->can('view', $member))->toBeTrue()
            ->and($user->can('create', Member::class))->toBeTrue()
            ->and($user->can('update', $member))->toBeTrue()
            ->and($user->can('manageMemberships', $member))->toBeTrue();
    }
});

it('redirects users with a temporary password before checking the module', function () {
    $user = User::factory()
        ->withGlobalPermission('members', PermissionLevel::Read)
        ->requiringPasswordChange()
        ->create();

    $this->actingAs($user)
        ->get(route('members.index'))
        ->assertRedirect(route('password.change.edit'));
});
