<?php

namespace App\Http\Controllers;

use App\Enums\Sex;
use App\Http\Requests\Member\IndexMemberRequest;
use App\Http\Requests\Member\StoreMemberRequest;
use App\Http\Requests\Member\UpdateMemberRequest;
use App\Models\Area;
use App\Models\Church;
use App\Models\Member;
use App\Models\Position;
use App\Models\State;
use App\Services\MemberService;
use App\Services\PermissionService;
use App\Services\UserService;
use App\Status;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MemberController extends Controller
{
    public function index(IndexMemberRequest $request, PermissionService $permissions): View
    {
        $filters = $request->validated();
        $members = Member::query()
            ->with(['primaryMembership.church', 'user:id,member_id'])
            ->when(! $request->user()->isGlobalAdministrator(), fn (Builder $query): Builder => $query->forChurch($permissions->currentChurch($request->user())?->id ?? ''))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query->search($search))
            ->when($filters['church_id'] ?? null, fn (Builder $query, string $churchId): Builder => $query->forChurch($churchId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['sex'] ?? null, fn (Builder $query, string $sex): Builder => $query->where('sex', $sex))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('members.index', [
            'members' => $members,
            'churches' => $permissions->availableChurches($request->user()),
            'filters' => $filters,
            'sexes' => Sex::cases(),
            'statuses' => [Status::Active, Status::Inactive],
            'hasActiveChurches' => Church::query()->where('status', Status::Active)->exists(),
        ]);
    }

    public function create(PermissionService $permissions): View
    {
        Gate::authorize('create', Member::class);

        return view('members.create', $this->formData($permissions));
    }

    public function store(StoreMemberRequest $request, MemberService $service, PermissionService $permissions): RedirectResponse
    {
        $validated = $request->validated();
        $church = Church::query()->where('status', Status::Active)->findOrFail($validated['church_id']);
        abort_unless($permissions->canAccessChurch($request->user(), $church), 404);
        $member = $service->create(
            $request->safe()->only($this->memberFields()),
            $church,
            $validated['joined_at'],
        );

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Membro cadastrado e vinculado à igreja com sucesso.');
    }

    public function show(Member $member, PermissionService $permissions, UserService $users): View
    {
        Gate::authorize('view', $member);
        $currentChurch = $permissions->currentChurch(request()->user());
        $member->load([
            'city.state',
            'user:id,member_id,display_name,username,status',
            'memberships' => fn ($query) => $query
                ->when(! request()->user()->isGlobalAdministrator(), fn ($query) => $query->where('church_id', $currentChurch?->id))
                ->with(['church', 'positionAssignments.position.department'])
                ->orderByDesc('is_primary')->orderByDesc('joined_at'),
        ]);
        $activeChurchIds = $member->memberships
            ->filter(fn ($membership): bool => $membership->status === Status::Active && $membership->ended_at === null)
            ->pluck('church_id');
        $eligibleAccessAssignments = $users->eligibleAssignments($member)
            ->when(! request()->user()->isGlobalAdministrator(), fn ($items) => $items->where('membership.church_id', $currentChurch?->id));

        return view('members.show', [
            'member' => $member,
            'availableChurches' => Church::query()
                ->when(! request()->user()->isGlobalAdministrator(), fn (Builder $query): Builder => $query->whereRaw('false'))
                ->where('status', Status::Active)
                ->whereNotIn('id', $activeChurchIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'positions' => Position::query()
                ->active()
                ->where('area_id', $currentChurch?->area_id ?? Area::query()->value('id'))
                ->with('department')
                ->orderBy('name')
                ->get(),
            'hasEligibleAccessPosition' => $eligibleAccessAssignments->isNotEmpty(),
        ]);
    }

    public function edit(Member $member, PermissionService $permissions): View
    {
        Gate::authorize('update', $member);
        $member->load('city');

        return view('members.edit', ['member' => $member] + $this->formData($permissions, $member));
    }

    public function update(UpdateMemberRequest $request, Member $member, MemberService $service): RedirectResponse
    {
        $service->update($member, $request->safe()->only($this->memberFields()));

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Dados do membro atualizados com sucesso.');
    }

    public function inactivate(Member $member, MemberService $service): RedirectResponse
    {
        Gate::authorize('inactivate', $member);
        $service->inactivate($member);

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Membro e vínculos ativos foram inativados. Um eventual acesso de usuário não foi alterado.');
    }

    /** @return array<string, mixed> */
    private function formData(PermissionService $permissions, ?Member $member = null): array
    {
        $selectedStateId = old('state_id', $member?->city?->state_id);

        return [
            'states' => State::query()->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'cities' => $selectedStateId
                ? State::query()->find($selectedStateId)?->cities()->orderBy('name')->get(['id', 'name']) ?? collect()
                : collect(),
            'churches' => $permissions->availableChurches(request()->user()),
            'sexes' => Sex::cases(),
        ];
    }

    /** @return list<string> */
    private function memberFields(): array
    {
        return [
            'name',
            'cpf',
            'email',
            'phone',
            'birth_date',
            'sex',
            'postal_code',
            'city_id',
            'street',
            'neighborhood',
            'number',
            'complement',
        ];
    }
}
