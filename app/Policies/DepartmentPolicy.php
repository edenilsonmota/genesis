<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class DepartmentPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'departments', PermissionLevel::Read);
    }

    public function view(User $user, Department $department): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'departments', PermissionLevel::Write);
    }

    public function update(User $user, Department $department): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Department $department): bool
    {
        return false;
    }
}
