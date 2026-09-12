<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class AreaPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'areas', PermissionLevel::Read);
    }

    public function view(User $user, Area $area): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdministrator();
    }

    public function update(User $user, Area $area): bool
    {
        return $user->isGlobalAdministrator();
    }

    public function delete(User $user, Area $area): bool
    {
        return false;
    }
}
