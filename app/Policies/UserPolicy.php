<?php

namespace App\Policies;

use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class UserPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'users', PermissionLevel::Read);
    }

    public function view(User $user, User $target): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }
        if ($user->isGlobalAdministrator()) {
            return true;
        }

        $church = $this->permissions->currentChurch($user);

        return ! $target->is_global_administrator
            && $church !== null
            && $this->permissions->availableChurches($target)->contains('id', $church->id);
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'users', PermissionLevel::Write);
    }

    public function activate(User $user, User $target): bool
    {
        return $this->canManage($user, $target);
    }

    public function inactivate(User $user, User $target): bool
    {
        return ! $user->is($target) && $this->canManage($user, $target);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->canManage($user, $target);
    }

    public function forcePasswordChange(User $user, User $target): bool
    {
        return $this->canManage($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }

    private function canManage(User $user, User $target): bool
    {
        if ($target->is_global_administrator || ! $this->create($user)) {
            return false;
        }

        if ($user->isGlobalAdministrator()) {
            return true;
        }

        $church = $this->permissions->currentChurch($user);

        return $church !== null
            && $this->permissions->availableChurches($target)->contains('id', $church->id);
    }
}
