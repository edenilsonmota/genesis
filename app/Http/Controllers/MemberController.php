<?php

namespace App\Http\Controllers;

use App\Enums\Sex;
use App\Http\Requests\Member\IndexMemberRequest;
use App\Http\Requests\Member\StoreMemberRequest;
use App\Http\Requests\Member\UpdateMemberRequest;
use App\Models\Church;
use App\Models\Member;
use App\Models\State;
use App\Services\MemberService;
use App\Status;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MemberController extends Controller
{
    public function index(IndexMemberRequest $request): View
    {
        $filters = $request->validated();
        $members = Member::query()
            ->with(['primaryMembership.church', 'user:id,member_id'])
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
            'churches' => Church::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
            'sexes' => Sex::cases(),
            'statuses' => [Status::Active, Status::Inactive],
            'hasActiveChurches' => Church::query()->where('status', Status::Active)->exists(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Member::class);

        return view('members.create', $this->formData());
    }

    public function store(StoreMemberRequest $request, MemberService $service): RedirectResponse
    {
        $validated = $request->validated();
        $church = Church::query()->where('status', Status::Active)->findOrFail($validated['church_id']);
        $member = $service->create(
            $request->safe()->only($this->memberFields()),
            $church,
            $validated['joined_at'],
        );

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Membro cadastrado e vinculado à igreja com sucesso.');
    }

    public function show(Member $member): View
    {
        Gate::authorize('view', $member);
        $member->load([
            'city.state',
            'user:id,member_id,display_name,username,status',
            'memberships' => fn ($query) => $query->with('church')->orderByDesc('is_primary')->orderByDesc('joined_at'),
        ]);
        $activeChurchIds = $member->memberships
            ->filter(fn ($membership): bool => $membership->status === Status::Active && $membership->ended_at === null)
            ->pluck('church_id');

        return view('members.show', [
            'member' => $member,
            'availableChurches' => Church::query()
                ->where('status', Status::Active)
                ->whereNotIn('id', $activeChurchIds)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function edit(Member $member): View
    {
        Gate::authorize('update', $member);
        $member->load('city');

        return view('members.edit', ['member' => $member] + $this->formData($member));
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
    private function formData(?Member $member = null): array
    {
        $selectedStateId = old('state_id', $member?->city?->state_id);

        return [
            'states' => State::query()->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'cities' => $selectedStateId
                ? State::query()->find($selectedStateId)?->cities()->orderBy('name')->get(['id', 'name']) ?? collect()
                : collect(),
            'churches' => Church::query()->where('status', Status::Active)->orderBy('name')->get(['id', 'name']),
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
