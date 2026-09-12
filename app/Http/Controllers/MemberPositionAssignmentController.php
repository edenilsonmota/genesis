<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberPositionAssignment\StoreMemberPositionAssignmentRequest;
use App\Models\Member;
use App\Models\MemberChurchMembership;
use App\Models\MemberPositionAssignment;
use App\Models\Position;
use App\Services\MemberPositionAssignmentService;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MemberPositionAssignmentController extends Controller
{
    public function store(StoreMemberPositionAssignmentRequest $request, Member $member, MemberPositionAssignmentService $service, PermissionService $permissions): RedirectResponse
    {
        $membership = MemberChurchMembership::query()->findOrFail($request->validated('member_church_membership_id'));
        if (! $request->user()->isGlobalAdministrator()) {
            abort_unless($membership->church_id === $permissions->currentChurch($request->user())?->id, 404);
        }
        $service->assign(
            $member,
            $membership,
            Position::query()->findOrFail($request->validated('position_id')),
            $request->validated('started_at'),
        );

        return back()->with('success', 'Cargo atribuído ao membro com sucesso.');
    }

    public function end(Request $request, Member $member, MemberPositionAssignment $assignment, MemberPositionAssignmentService $service, PermissionService $permissions): RedirectResponse
    {
        Gate::authorize('managePositions', $member);
        if (! $request->user()->isGlobalAdministrator()) {
            $assignment->loadMissing('membership');
            abort_unless($assignment->membership->church_id === $permissions->currentChurch($request->user())?->id, 404);
        }
        $validated = $request->validate(['ended_at' => ['required', 'date']]);
        $service->end($member, $assignment, $validated['ended_at']);

        return back()->with('success', 'Cargo encerrado com sucesso.');
    }
}
