<?php

namespace App\Policies;

use App\Models\FinancialCategory;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class FinancialCategoryPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'finance.categories', PermissionLevel::Read);
    }

    public function view(User $user, FinancialCategory $financialCategory): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        if ($user->isGlobalAdministrator()) {
            return true;
        }

        return $this->permissions->currentChurch($user)?->area_id === $financialCategory->area_id;
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'finance.categories', PermissionLevel::Write);
    }

    public function update(User $user, FinancialCategory $financialCategory): bool
    {
        if ($financialCategory->fixed || ! $this->create($user)) {
            return false;
        }

        return $user->isGlobalAdministrator()
            || $this->permissions->currentChurch($user)?->area_id === $financialCategory->area_id;
    }

    public function delete(User $user, FinancialCategory $financialCategory): bool
    {
        return false;
    }
}
