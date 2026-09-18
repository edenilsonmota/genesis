<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\MemberImport;
use App\Models\User;
use App\PermissionLevel;
use App\Services\PermissionService;

class MemberImportPolicy
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->can($user, 'members.import', PermissionLevel::Read);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MemberImport $memberImport): bool
    {
        $church = $memberImport->church()->first();

        return $church !== null && $this->viewForChurch($user, $church);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->permissions->can($user, 'members.import', PermissionLevel::Write);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function createForChurch(User $user, Church $church): bool
    {
        return $this->permissions->canAccessChurch($user, $church)
            && $this->permissions->can($user, 'members.import', PermissionLevel::Write, $church);
    }

    public function viewForChurch(User $user, Church $church): bool
    {
        return $this->permissions->canAccessChurch($user, $church)
            && $this->permissions->can($user, 'members.import', PermissionLevel::Read, $church);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function exportForChurch(User $user, Church $church): bool
    {
        return $this->permissions->canAccessChurch($user, $church)
            && $this->permissions->can($user, 'members.import', PermissionLevel::Read, $church)
            && $this->permissions->can($user, 'members', PermissionLevel::Read, $church);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function confirm(User $user, MemberImport $memberImport): bool
    {
        $church = $memberImport->church()->first();

        return $this->view($user, $memberImport)
            && $church !== null
            && $this->permissions->can($user, 'members.import', PermissionLevel::Write, $church);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function download(User $user, MemberImport $memberImport): bool
    {
        return $this->view($user, $memberImport);
    }
}
