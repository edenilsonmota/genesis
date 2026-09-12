<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class PositionPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'positions', PermissionLevel::Read);
    }

    public function view(User $user, Position $position): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'positions', PermissionLevel::Write);
    }

    public function update(User $user, Position $position): bool
    {
        return ! $position->fixed && $this->create($user);
    }

    public function updatePermissions(User $user, Position $position): bool
    {
        return ! $position->fixed && $position->grants_system_access && $this->create($user);
    }

    public function delete(User $user, Position $position): bool
    {
        return false;
    }
}
