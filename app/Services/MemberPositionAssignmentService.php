<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberPositionAssignment;
use App\Models\Position;
use App\Status;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberPositionAssignmentService
{
    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
    ) {}

    public function assign(Member $member, MemberChurchMembership $membership, Position $position, string $startedAt): MemberPositionAssignment
    {
        try {
            return DB::transaction(function () use ($member, $membership, $position, $startedAt): MemberPositionAssignment {
                $lockedMembership = MemberChurchMembership::query()->with('church')->where('member_id', $member->id)->lockForUpdate()->findOrFail($membership->id);
                $lockedPosition = Position::query()->with('department')->lockForUpdate()->findOrFail($position->id);
                $date = CarbonImmutable::parse($startedAt)->startOfDay();

                if ($lockedMembership->status !== Status::Active || $lockedMembership->joined_at->isAfter($date) || ($lockedMembership->ended_at !== null && $lockedMembership->ended_at->isBefore($date))) {
                    throw ValidationException::withMessages(['member_church_membership_id' => 'O vínculo com a igreja precisa estar ativo na data inicial.']);
                }
                if ($lockedPosition->status !== Status::Active || $lockedPosition->area_id !== $lockedMembership->church->area_id) {
                    throw ValidationException::withMessages(['position_id' => 'Selecione um cargo ativo da mesma área da igreja.']);
                }
                $assignment = MemberPositionAssignment::query()->create([
                    'member_church_membership_id' => $lockedMembership->id,
                    'position_id' => $lockedPosition->id,
                    'status' => Status::Active,
                    'started_at' => $date,
                    'ended_at' => null,
                ]);
                $this->audit->record('member_position.assigned', 'member_position_assignments', $assignment, 'church', $lockedMembership->church_id, [
                    'member_id' => $member->id,
                    'position_id' => $lockedPosition->id,
                    'started_at' => $date->toDateString(),
                ]);

                return $assignment;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['position_id' => 'Este cargo já está ativo para o membro nesta igreja.']);
            }
            throw $exception;
        }
    }

    public function end(Member $member, MemberPositionAssignment $assignment, string $endedAt): MemberPositionAssignment
    {
        return DB::transaction(function () use ($member, $assignment, $endedAt): MemberPositionAssignment {
            $locked = MemberPositionAssignment::query()->with(['membership.church', 'membership.member.user', 'position.permissions.permissionModule'])
                ->whereHas('membership', fn ($query) => $query->where('member_id', $member->id))
                ->lockForUpdate()
                ->findOrFail($assignment->id);
            $date = CarbonImmutable::parse($endedAt)->startOfDay();
            if ($locked->status !== Status::Active || $locked->ended_at !== null) {
                throw ValidationException::withMessages(['assignment' => 'Este cargo já foi encerrado.']);
            }
            if ($date->isBefore($locked->started_at)) {
                throw ValidationException::withMessages(['ended_at' => 'A data final não pode ser anterior à data inicial.']);
            }

            $user = $locked->membership->member->user;
            $isAdministrator = $locked->position->grants_system_access
                && $locked->position->status === Status::Active
                && $locked->position->permissions->contains(fn ($permission): bool => $permission->permissionModule->key === 'users' && $permission->level->value === 'write');
            if ($user !== null && $isAdministrator && ! $this->permissions->hasOtherChurchAdministrator($locked->membership->church, excludedAssignmentId: $locked->id)) {
                throw ValidationException::withMessages(['assignment' => 'Não é possível encerrar o cargo do último administrador válido desta igreja.']);
            }

            $locked->update(['status' => Status::Inactive, 'ended_at' => $date]);
            $this->audit->record('member_position.ended', 'member_position_assignments', $locked, 'church', $locked->membership->church_id, [
                'member_id' => $member->id,
                'position_id' => $locked->position_id,
                'ended_at' => $date->toDateString(),
            ]);

            return $locked->refresh();
        });
    }
}
