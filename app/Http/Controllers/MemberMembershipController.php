<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberMembership\StoreMemberMembershipRequest;
use App\Http\Requests\MemberMembership\UpdateMemberMembershipRequest;
use App\Models\Church;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Services\MemberMembershipService;
use App\Status;
use Illuminate\Http\RedirectResponse;

class MemberMembershipController extends Controller
{
    public function store(
        StoreMemberMembershipRequest $request,
        Member $member,
        MemberMembershipService $service,
    ): RedirectResponse {
        $church = Church::query()
            ->where('status', Status::Active)
            ->findOrFail($request->validated('church_id'));
        $service->add($member, $church, $request->validated('joined_at'));

        return back()->with('success', 'Igreja vinculada ao membro com sucesso.');
    }

    public function primary(
        UpdateMemberMembershipRequest $request,
        Member $member,
        MemberChurchMembership $membership,
        MemberMembershipService $service,
    ): RedirectResponse {
        $service->markPrimary($member, $membership);

        return back()->with('success', 'Igreja principal atualizada com sucesso.');
    }

    public function end(
        UpdateMemberMembershipRequest $request,
        Member $member,
        MemberChurchMembership $membership,
        MemberMembershipService $service,
    ): RedirectResponse {
        $service->end($member, $membership);

        return back()->with('success', 'Vínculo encerrado com sucesso.');
    }
}
