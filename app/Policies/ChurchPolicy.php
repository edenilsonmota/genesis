<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;

class ChurchPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdministrator();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Church $church): bool
    {
        return $user->isGlobalAdministrator();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isGlobalAdministrator();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Church $church): bool
    {
        return $user->isGlobalAdministrator();
    }

    public function inactivate(User $user, Church $church): bool
    {
        return $user->isGlobalAdministrator();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Church $church): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Church $church): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Church $church): bool
    {
        return false;
    }
}
