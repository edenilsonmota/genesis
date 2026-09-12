<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Member;
use App\Models\MemberPositionAssignment;
use App\Models\User;
use App\PermissionLevel;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
    ) {}

    /** @return array{0: User, 1: string} */
    public function create(Member $member, string $username, ?Church $scopeChurch = null): array
    {
        return DB::transaction(function () use ($member, $username, $scopeChurch): array {
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->id);
            if ($lockedMember->user()->exists()) {
                throw ValidationException::withMessages(['member_id' => 'Este membro já possui uma conta de acesso.']);
            }
            $eligibleAssignments = $this->eligibleAssignments($lockedMember)
                ->when($scopeChurch !== null, fn (Collection $assignments): Collection => $assignments
                    ->where('membership.church_id', $scopeChurch->id));
            if ($lockedMember->status !== Status::Active || $eligibleAssignments->isEmpty()) {
                throw ValidationException::withMessages(['member_id' => 'O membro precisa possuir ao menos um cargo ativo que conceda acesso ao sistema.']);
            }

            $password = Str::password(20);
            $user = User::query()->create([
                'member_id' => $lockedMember->id,
                'display_name' => $lockedMember->name,
                'username' => $username,
                'password' => $password,
                'status' => Status::Active,
                'must_change_password' => true,
            ]);
            $this->audit->record('user.created', 'users', $user, 'area', $eligibleAssignments->first()->position->area_id, [
                'member_id' => $lockedMember->id,
                'username' => $user->username,
            ]);

            return [$user, $password];
        });
    }

    public function changeStatus(User $user, Status $status): User
    {
        return DB::transaction(function () use ($user, $status): User {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->is_global_administrator) {
                throw ValidationException::withMessages(['status' => 'O administrador global só pode ser alterado pelo Seeder protegido.']);
            }
            if ($status === Status::Active && ($locked->member_id === null
                || ! $locked->member()->where('status', Status::Active->value)->exists()
                || $this->permissions->availableChurches($locked)->isEmpty())) {
                throw ValidationException::withMessages(['status' => 'O usuário não possui cargo válido que conceda acesso ao sistema.']);
            }
            if ($status === Status::Inactive) {
                foreach ($this->permissions->availableChurches($locked) as $church) {
                    if ($this->permissions->can($locked, 'users', PermissionLevel::Write, $church)
                        && ! $this->permissions->hasOtherChurchAdministrator($church, excludedUserId: $locked->id)) {
                        throw ValidationException::withMessages(['status' => "Não é possível inativar o último administrador válido de {$church->name}."]);
                    }
                }
            }

            $locked->update(['status' => $status]);
            $this->audit->record('user.status_changed', 'users', $locked, null, null, ['status' => $status->value]);

            return $locked->refresh();
        });
    }

    public function resetPassword(User $user): string
    {
        return DB::transaction(function () use ($user): string {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->is_global_administrator) {
                throw ValidationException::withMessages(['user' => 'O administrador global só pode ser alterado pelo Seeder protegido.']);
            }
            $password = Str::password(20);
            $locked->update(['password' => $password, 'must_change_password' => true]);
            $this->audit->record('user.temporary_password_generated', 'users', $locked, details: ['must_change_password' => true]);

            return $password;
        });
    }

    public function requirePasswordChange(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($locked->is_global_administrator) {
                throw ValidationException::withMessages(['user' => 'O administrador global só pode ser alterado pelo Seeder protegido.']);
            }
            $locked->update(['must_change_password' => true]);
            $this->audit->record('user.password_change_required', 'users', $locked, details: ['must_change_password' => true]);
        });
    }

    /** @return Collection<int, MemberPositionAssignment> */
    public function eligibleAssignments(Member $member): Collection
    {
        $today = today()->toDateString();

        return MemberPositionAssignment::query()
            ->with(['membership.church', 'position.department', 'position.permissions.permissionModule'])
            ->effectiveOn($today)
            ->whereHas('membership', fn (Builder $query): Builder => $query
                ->where('member_id', $member->id)
                ->effectiveOn($today)
                ->whereHas('church', fn (Builder $query): Builder => $query->active()))
            ->whereHas('position', fn (Builder $query): Builder => $query
                ->active()
                ->where('grants_system_access', true))
            ->get()
            ->filter(fn (MemberPositionAssignment $assignment): bool => $assignment->position->area_id === $assignment->membership->church->area_id)
            ->values();
    }
}
