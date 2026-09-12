<?php

namespace App\Policies;

use App\Models\FinancialAccount;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class FinancialAccountPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'finance.accounts', PermissionLevel::Read);
    }

    public function view(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->canUseAccount($user, $financialAccount, PermissionLevel::Read);
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'finance.accounts', PermissionLevel::Write);
    }

    public function update(User $user, FinancialAccount $financialAccount): bool
    {
        return $this->canUseAccount($user, $financialAccount, PermissionLevel::Write);
    }

    public function delete(User $user, FinancialAccount $financialAccount): bool
    {
        return false;
    }

    private function canUseAccount(User $user, FinancialAccount $financialAccount, PermissionLevel $level): bool
    {
        if ($user->isGlobalAdministrator()) {
            return true;
        }

        $church = $this->permissions->currentChurch($user);

        return $church !== null
            && $financialAccount->area_id === null
            && $financialAccount->church_id === $church->id
            && $this->permissions->can($user, 'finance.accounts', $level, $church);
    }
}
