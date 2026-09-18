<?php

namespace App\Services;

use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\PermissionModule;
use App\Models\User;
use App\PermissionLevel;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    public function hasSystemAccess(User $user): bool
    {
        if ($user->isGlobalAdministrator()) {
            return true;
        }

        return $user->isActive()
            && $user->member_id !== null
            && $user->member()->where('status', Status::Active->value)->exists()
            && $this->availableChurchesQuery($user)->exists();
    }

    /** @return Collection<int, Church> */
    public function availableChurches(User $user): Collection
    {
        if ($user->isGlobalAdministrator()) {
            return Church::query()->active()->orderBy('name')->get();
        }

        return $this->availableChurchesQuery($user)->orderBy('name')->get();
    }

    public function currentChurch(User $user): ?Church
    {
        if ($user->isGlobalAdministrator() && in_array(session('active_church_id'), [null, '__overview__'], true)) {
            return null;
        }

        $available = $this->availableChurches($user);
        $requestedId = session('active_church_id');
        $church = $available->firstWhere('id', $requestedId) ?? $available->first();

        if ($church !== null && $church->id !== $requestedId) {
            session(['active_church_id' => $church->id]);
        }

        return $church;
    }

    public function can(User $user, string $moduleKey, PermissionLevel $requiredLevel, ?Church $church = null): bool
    {
        if ($user->isGlobalAdministrator()) {
            return true;
        }

        if (! $user->isActive() || $user->member_id === null || ! $user->member()->where('status', Status::Active->value)->exists()) {
            return false;
        }

        $church ??= $this->currentChurch($user);

        if ($church === null) {
            return false;
        }

        $acceptedLevels = $requiredLevel === PermissionLevel::Write
            ? [PermissionLevel::Write->value]
            : [PermissionLevel::Read->value, PermissionLevel::Write->value];

        return $this->eligibleAssignmentsQuery($user, $church)
            ->join('position_permissions', 'position_permissions.position_id', '=', 'positions.id')
            ->join('permission_modules', 'permission_modules.id', '=', 'position_permissions.permission_module_id')
            ->where('permission_modules.key', $moduleKey)
            ->where('permission_modules.status', Status::Active->value)
            ->whereIn('position_permissions.level', $acceptedLevels)
            ->exists();
    }

    /** @return array<string, PermissionLevel> */
    public function effectivePermissions(User $user, Church $church): array
    {
        if ($user->isGlobalAdministrator()) {
            return PermissionModule::query()
                ->where('status', Status::Active->value)
                ->pluck('key')
                ->mapWithKeys(fn (string $key): array => [$key => PermissionLevel::Write])
                ->all();
        }

        $permissions = [];
        $rows = $this->eligibleAssignmentsQuery($user, $church)
            ->join('position_permissions', 'position_permissions.position_id', '=', 'positions.id')
            ->join('permission_modules', 'permission_modules.id', '=', 'position_permissions.permission_module_id')
            ->where('permission_modules.status', Status::Active->value)
            ->get(['permission_modules.key', 'position_permissions.level']);

        foreach ($rows as $row) {
            $level = PermissionLevel::from($row->level);
            if (! isset($permissions[$row->key]) || $level === PermissionLevel::Write) {
                $permissions[$row->key] = $level;
            }
        }

        return $permissions;
    }

    public function canAccessChurch(User $user, Church $church): bool
    {
        return $user->isGlobalAdministrator()
            || ($user->isActive() && $this->availableChurchesQuery($user)->whereKey($church->id)->exists());
    }

    /** @return Collection<int, string> */
    public function activeDepartmentIds(User $user, Church $church): Collection
    {
        if ($user->isGlobalAdministrator()) {
            return DB::table('departments')
                ->where('area_id', $church->area_id)
                ->where('status', Status::Active->value)
                ->pluck('id');
        }

        return $this->eligibleAssignmentsQuery($user, $church)
            ->whereNotNull('positions.department_id')
            ->distinct()
            ->pluck('positions.department_id');
    }

    public function hasActiveDepartmentAssignment(User $user, Church $church, string $departmentId): bool
    {
        return $user->isGlobalAdministrator()
            || $this->eligibleAssignmentsQuery($user, $church)
                ->where('positions.department_id', $departmentId)
                ->exists();
    }

    /** @return Collection<int, Church> */
    public function churchesWithPermission(User $user, string $moduleKey, PermissionLevel $level): Collection
    {
        return $this->availableChurches($user)
            ->filter(fn (Church $church): bool => $this->can($user, $moduleKey, $level, $church))
            ->values();
    }

    public function canUseFinancialAccount(
        User $user,
        FinancialAccount $financialAccount,
        string $moduleKey,
        PermissionLevel $level,
    ): bool {
        if ($user->isGlobalAdministrator()) {
            return true;
        }

        if ($financialAccount->area_id !== null || $financialAccount->church_id === null) {
            return false;
        }

        $church = $financialAccount->relationLoaded('church')
            ? $financialAccount->church
            : Church::query()->find($financialAccount->church_id);

        return $church !== null
            && $this->canAccessChurch($user, $church)
            && $this->can($user, $moduleKey, $level, $church);
    }

    public function constrainUsersToChurch(Builder $query, Church $church): Builder
    {
        $today = today()->toDateString();

        return $query->whereHas('member.memberships', fn (Builder $query): Builder => $query
            ->where('church_id', $church->id)
            ->effectiveOn($today)
            ->whereHas('positionAssignments', fn (Builder $query): Builder => $query
                ->effectiveOn($today)
                ->whereHas('position', fn (Builder $query): Builder => $query
                    ->where('area_id', $church->area_id)
                    ->where('status', Status::Active->value)
                    ->where('grants_system_access', true))));
    }

    public function hasOtherChurchAdministrator(
        Church $church,
        ?string $excludedUserId = null,
        ?string $excludedPositionId = null,
        ?string $excludedAssignmentId = null,
    ): bool {
        $today = today()->toDateString();

        return DB::table('users')
            ->join('members', 'members.id', '=', 'users.member_id')
            ->join('member_church_memberships as memberships', function ($join) use ($church): void {
                $join->on('memberships.member_id', '=', 'members.id')
                    ->where('memberships.church_id', $church->id);
            })
            ->join('member_position_assignments as assignments', 'assignments.member_church_membership_id', '=', 'memberships.id')
            ->join('positions', 'positions.id', '=', 'assignments.position_id')
            ->join('position_permissions', 'position_permissions.position_id', '=', 'positions.id')
            ->join('permission_modules', 'permission_modules.id', '=', 'position_permissions.permission_module_id')
            ->where('users.status', Status::Active->value)
            ->where('members.status', Status::Active->value)
            ->where('memberships.status', Status::Active->value)
            ->whereDate('memberships.joined_at', '<=', $today)
            ->where(fn ($query) => $query->whereNull('memberships.ended_at')->orWhereDate('memberships.ended_at', '>=', $today))
            ->where('assignments.status', Status::Active->value)
            ->whereDate('assignments.started_at', '<=', $today)
            ->where(fn ($query) => $query->whereNull('assignments.ended_at')->orWhereDate('assignments.ended_at', '>=', $today))
            ->where('positions.status', Status::Active->value)
            ->where('positions.grants_system_access', true)
            ->where('positions.area_id', $church->area_id)
            ->where('permission_modules.key', 'users')
            ->where('permission_modules.status', Status::Active->value)
            ->where('position_permissions.level', PermissionLevel::Write->value)
            ->when($excludedUserId, fn ($query, string $id) => $query->where('users.id', '!=', $id))
            ->when($excludedPositionId, fn ($query, string $id) => $query->where('positions.id', '!=', $id))
            ->when($excludedAssignmentId, fn ($query, string $id) => $query->where('assignments.id', '!=', $id))
            ->exists();
    }

    private function availableChurchesQuery(User $user): Builder
    {
        $today = today()->toDateString();

        return Church::query()
            ->active()
            ->whereHas('memberMemberships', fn (Builder $query): Builder => $query
                ->where('member_id', $user->member_id)
                ->effectiveOn($today)
                ->whereHas('positionAssignments', fn (Builder $query): Builder => $query
                    ->effectiveOn($today)
                    ->whereHas('position', fn (Builder $query): Builder => $query
                        ->whereColumn('positions.area_id', 'churches.area_id')
                        ->where('status', Status::Active->value)
                        ->where('grants_system_access', true))));
    }

    private function eligibleAssignmentsQuery(User $user, Church $church): \Illuminate\Database\Query\Builder
    {
        $today = today()->toDateString();

        return DB::table('member_position_assignments as assignments')
            ->join('member_church_memberships as memberships', 'memberships.id', '=', 'assignments.member_church_membership_id')
            ->join('positions', 'positions.id', '=', 'assignments.position_id')
            ->where('memberships.member_id', $user->member_id)
            ->where('memberships.church_id', $church->id)
            ->where('memberships.status', Status::Active->value)
            ->whereDate('memberships.joined_at', '<=', $today)
            ->where(fn ($query) => $query->whereNull('memberships.ended_at')->orWhereDate('memberships.ended_at', '>=', $today))
            ->where('assignments.status', Status::Active->value)
            ->whereDate('assignments.started_at', '<=', $today)
            ->where(fn ($query) => $query->whereNull('assignments.ended_at')->orWhereDate('assignments.ended_at', '>=', $today))
            ->where('positions.area_id', $church->area_id)
            ->where('positions.status', Status::Active->value)
            ->where('positions.grants_system_access', true);
    }
}
