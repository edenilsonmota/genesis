<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Status;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberMembershipService
{
    public function createInitial(Member $member, Church $church, string $joinedAt): MemberChurchMembership
    {
        return $this->createMembership($member, $church, $joinedAt, true);
    }

    public function add(Member $member, Church $church, string $joinedAt): MemberChurchMembership
    {
        return $this->createMembership($member, $church, $joinedAt, false);
    }

    public function markPrimary(Member $member, MemberChurchMembership $membership): MemberChurchMembership
    {
        return DB::transaction(function () use ($member, $membership): MemberChurchMembership {
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->getKey());
            $target = $this->lockedMembership($lockedMember, $membership);

            if ($lockedMember->status !== Status::Active || ! $this->isCurrent($target)) {
                throw ValidationException::withMessages([
                    'membership' => 'Apenas um vínculo ativo de um membro ativo pode ser definido como principal.',
                ]);
            }

            MemberChurchMembership::query()
                ->where('member_id', $lockedMember->getKey())
                ->active()
                ->lockForUpdate()
                ->get();

            MemberChurchMembership::query()
                ->where('member_id', $lockedMember->getKey())
                ->active()
                ->whereKeyNot($target->getKey())
                ->update(['is_primary' => false]);

            $target->update(['is_primary' => true]);

            return $target->refresh();
        });
    }

    public function end(Member $member, MemberChurchMembership $membership): MemberChurchMembership
    {
        return DB::transaction(function () use ($member, $membership): MemberChurchMembership {
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->getKey());
            $lockedTarget = $this->lockedMembership($lockedMember, $membership);
            $activeMemberships = MemberChurchMembership::query()
                ->where('member_id', $lockedMember->getKey())
                ->active()
                ->lockForUpdate()
                ->get();
            $target = $activeMemberships->firstWhere('id', $lockedTarget->getKey());

            if (! $target instanceof MemberChurchMembership) {
                throw ValidationException::withMessages([
                    'membership' => 'Este vínculo já foi encerrado.',
                ]);
            }

            if ($activeMemberships->count() === 1) {
                throw ValidationException::withMessages([
                    'membership' => 'Inative o membro para encerrar seu único vínculo ativo.',
                ]);
            }

            if ($target->is_primary) {
                throw ValidationException::withMessages([
                    'membership' => 'Defina outra igreja como principal antes de encerrar este vínculo.',
                ]);
            }

            $target->update([
                'status' => Status::Inactive,
                'is_primary' => false,
                'ended_at' => today(),
            ]);

            return $target->refresh();
        });
    }

    private function createMembership(
        Member $member,
        Church $church,
        string $joinedAt,
        bool $initial,
    ): MemberChurchMembership {
        try {
            return DB::transaction(function () use ($member, $church, $joinedAt, $initial): MemberChurchMembership {
                $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->getKey());
                $lockedChurch = Church::query()->lockForUpdate()->findOrFail($church->getKey());
                $date = CarbonImmutable::parse($joinedAt)->startOfDay();

                if ($lockedMember->status !== Status::Active) {
                    throw ValidationException::withMessages([
                        'membership' => 'Não é possível adicionar uma igreja a um membro inativo.',
                    ]);
                }

                if ($lockedChurch->status !== Status::Active) {
                    throw ValidationException::withMessages([
                        'church_id' => 'Selecione uma igreja ativa.',
                    ]);
                }

                if ($date->isFuture()) {
                    throw ValidationException::withMessages([
                        'joined_at' => 'A data de entrada não pode estar no futuro.',
                    ]);
                }

                $activeMemberships = MemberChurchMembership::query()
                    ->where('member_id', $lockedMember->getKey())
                    ->active()
                    ->lockForUpdate()
                    ->get();

                if ($initial && $activeMemberships->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'membership' => 'O vínculo inicial só pode ser criado para um membro sem igrejas ativas.',
                    ]);
                }

                if ($activeMemberships->contains('church_id', $lockedChurch->getKey())) {
                    throw ValidationException::withMessages([
                        'church_id' => 'O membro já possui vínculo ativo com esta igreja.',
                    ]);
                }

                return $lockedMember->memberships()->create([
                    'church_id' => $lockedChurch->getKey(),
                    'status' => Status::Active,
                    'is_primary' => $initial || $activeMemberships->isEmpty(),
                    'joined_at' => $date,
                    'ended_at' => null,
                ]);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages([
                    'church_id' => 'Este vínculo ativo já existe ou conflita com a igreja principal.',
                ]);
            }

            throw $exception;
        }
    }

    private function lockedMembership(Member $member, MemberChurchMembership $membership): MemberChurchMembership
    {
        return MemberChurchMembership::query()
            ->where('member_id', $member->getKey())
            ->lockForUpdate()
            ->findOrFail($membership->getKey());
    }

    private function isCurrent(MemberChurchMembership $membership): bool
    {
        return $membership->status === Status::Active && $membership->ended_at === null;
    }
}
