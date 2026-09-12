<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;
use App\PermissionLevel;

class MemberPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasGlobalPermission('members', PermissionLevel::Read);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Member $member): bool
    {
        return $user->hasGlobalPermission('members', PermissionLevel::Read);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasGlobalPermission('members', PermissionLevel::Write);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Member $member): bool
    {
        return $user->hasGlobalPermission('members', PermissionLevel::Write);
    }

    public function inactivate(User $user, Member $member): bool
    {
        return $user->hasGlobalPermission('members', PermissionLevel::Write);
    }

    public function manageMemberships(User $user, Member $member): bool
    {
        return $user->hasGlobalPermission('members', PermissionLevel::Write);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Member $member): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Member $member): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Member $member): bool
    {
        return false;
    }
}
