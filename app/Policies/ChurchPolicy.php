<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class ChurchPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'churches', PermissionLevel::Read);
    }

    public function view(User $user, Church $church): bool
    {
        return $this->permissions->can($user, 'churches', PermissionLevel::Read, $church)
            && $this->permissions->canAccessChurch($user, $church);
    }

    public function create(User $user): bool
    {
        return $user->isGlobalAdministrator();
    }

    public function update(User $user, Church $church): bool
    {
        return $user->isGlobalAdministrator();
    }

    public function inactivate(User $user, Church $church): bool
    {
        return $user->isGlobalAdministrator();
    }

    public function delete(User $user, Church $church): bool
    {
        return false;
    }
}
