<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class MemberPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'members', PermissionLevel::Read);
    }

    public function view(User $user, Member $member): bool
    {
        if ($user->isGlobalAdministrator()) {
            return true;
        }

        $church = $this->permissions->currentChurch($user);

        return $church !== null
            && $this->viewAny($user)
            && $member->memberships()->effectiveOn(today()->toDateString())->where('church_id', $church->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'members', PermissionLevel::Write);
    }

    public function update(User $user, Member $member): bool
    {
        return $this->create($user) && $this->view($user, $member);
    }

    public function inactivate(User $user, Member $member): bool
    {
        return $user->isGlobalAdministrator();
    }

    public function manageMemberships(User $user, Member $member): bool
    {
        return $user->isGlobalAdministrator();
    }

    public function managePositions(User $user, Member $member): bool
    {
        return $this->permissions->can($user, 'positions', PermissionLevel::Write) && $this->view($user, $member);
    }

    public function delete(User $user, Member $member): bool
    {
        return false;
    }
}
