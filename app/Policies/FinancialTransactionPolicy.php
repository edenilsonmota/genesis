<?php

namespace App\Policies;

use App\Enums\FinancialTransactionStatus;
use App\Enums\FinancialTransactionType;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class FinancialTransactionPolicy
{
    public function __construct(private PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'finance.transactions', PermissionLevel::Read);
    }

    public function view(User $user, FinancialTransaction $financialTransaction): bool
    {
        if ($user->isGlobalAdministrator()) {
            return true;
        }

        $financialTransaction->loadMissing('movements.account.church');

        return $financialTransaction->movements->contains(
            fn ($movement): bool => $this->permissions->canUseFinancialAccount(
                $user,
                $movement->account,
                'finance.transactions',
                PermissionLevel::Read,
            ),
        );
    }

    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'finance.transactions', PermissionLevel::Write);
    }

    public function update(User $user, FinancialTransaction $financialTransaction): bool
    {
        return $financialTransaction->isEditable()
            && $this->canWriteEveryAccount($user, $financialTransaction);
    }

    public function delete(User $user, FinancialTransaction $financialTransaction): bool
    {
        return false;
    }

    public function settle(User $user, FinancialTransaction $financialTransaction): bool
    {
        return $financialTransaction->isEditable()
            && $this->canWriteEveryAccount($user, $financialTransaction);
    }

    public function cancel(User $user, FinancialTransaction $financialTransaction): bool
    {
        return $financialTransaction->isEditable()
            && $this->canWriteEveryAccount($user, $financialTransaction);
    }

    public function reverse(User $user, FinancialTransaction $financialTransaction): bool
    {
        return $financialTransaction->status === FinancialTransactionStatus::Settled
            && $financialTransaction->reversed_at === null
            && $financialTransaction->type !== FinancialTransactionType::Reversal
            && $this->canWriteEveryAccount($user, $financialTransaction);
    }

    private function canWriteEveryAccount(User $user, FinancialTransaction $financialTransaction): bool
    {
        if ($user->isGlobalAdministrator()) {
            return true;
        }

        $financialTransaction->loadMissing('movements.account.church');

        return $financialTransaction->movements->isNotEmpty()
            && $financialTransaction->movements->every(
                fn ($movement): bool => $this->permissions->canUseFinancialAccount(
                    $user,
                    $movement->account,
                    'finance.transactions',
                    PermissionLevel::Write,
                ),
            );
    }
}
