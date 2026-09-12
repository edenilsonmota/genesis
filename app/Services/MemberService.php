<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Status;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberService
{
    public function __construct(private readonly MemberMembershipService $memberships) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, Church $church, string $joinedAt): Member
    {
        try {
            return DB::transaction(function () use ($attributes, $church, $joinedAt): Member {
                $member = Member::query()->create([...$attributes, 'status' => Status::Active]);
                $this->memberships->createInitial($member, $church, $joinedAt);

                return $member;
            });
        } catch (QueryException $exception) {
            $this->throwCpfValidation($exception);

            throw $exception;
        }
    }

    /** @param array<string, mixed> $attributes */
    public function update(Member $member, array $attributes): Member
    {
        try {
            return DB::transaction(function () use ($member, $attributes): Member {
                $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->getKey());
                $lockedMember->update($attributes);

                return $lockedMember;
            });
        } catch (QueryException $exception) {
            $this->throwCpfValidation($exception);

            throw $exception;
        }
    }

    public function inactivate(Member $member): Member
    {
        return DB::transaction(function () use ($member): Member {
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->getKey());

            MemberChurchMembership::query()
                ->where('member_id', $lockedMember->getKey())
                ->active()
                ->lockForUpdate()
                ->get();

            MemberChurchMembership::query()
                ->where('member_id', $lockedMember->getKey())
                ->active()
                ->update([
                    'status' => Status::Inactive,
                    'is_primary' => false,
                    'ended_at' => today(),
                ]);

            $lockedMember->update(['status' => Status::Inactive]);

            return $lockedMember;
        });
    }

    private function throwCpfValidation(QueryException $exception): void
    {
        if ($exception->getCode() === '23505' && str_contains($exception->getMessage(), 'members_cpf_unique')) {
            throw ValidationException::withMessages([
                'cpf' => 'Já existe um membro cadastrado com este CPF.',
            ]);
        }
    }
}
