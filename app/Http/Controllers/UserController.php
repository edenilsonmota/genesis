<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Models\Church;
use App\Models\Member;
use App\Models\User;
use App\Services\PermissionService;
use App\Services\UserService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(IndexUserRequest $request, PermissionService $permissions): View
    {
        $filters = $request->validated();
        $actor = $request->user();
        $church = $actor->isGlobalAdministrator() ? null : $permissions->currentChurch($actor);
        if (isset($filters['church_id'])) {
            $church = Church::query()->findOrFail($filters['church_id']);
            abort_unless($permissions->canAccessChurch($actor, $church), 404);
        }

        $users = User::query()
            ->with([
                'member.memberships' => fn ($query) => $query
                    ->when(! $actor->isGlobalAdministrator(), fn ($query) => $query->where('church_id', $church?->id)),
                'member.memberships.church',
                'member.memberships.positionAssignments.position.department',
            ])
            ->when(! $actor->isGlobalAdministrator(), fn (Builder $query): Builder => $query
                ->where('is_global_administrator', false)
                ->when($church !== null, fn (Builder $query): Builder => $permissions->constrainUsersToChurch($query, $church)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('display_name', 'ILIKE', "%{$search}%")
                    ->orWhere('username', 'ILIKE', "%{$search}%")
                    ->orWhereHas('member', fn (Builder $query): Builder => $query->where('name', 'ILIKE', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($church !== null && $actor->isGlobalAdministrator(), fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('is_global_administrator', true)
                    ->orWhere(fn (Builder $query): Builder => $permissions->constrainUsersToChurch($query, $church))))
            ->orderBy('display_name')->orderBy('id')->paginate(10)->withQueryString();

        return view('users.index', [
            'users' => $users,
            'filters' => $filters,
            'churches' => $permissions->availableChurches($actor),
            'selectedChurch' => $church,
        ]);
    }

    public function create(UserService $service, PermissionService $permissions): View
    {
        Gate::authorize('create', User::class);
        $actor = request()->user();
        $scopeChurch = $actor->isGlobalAdministrator() ? null : $permissions->currentChurch($actor);
        $members = Member::query()->where('status', Status::Active->value)->doesntHave('user')
            ->when($scopeChurch !== null, fn (Builder $query): Builder => $query->forChurch($scopeChurch->id))
            ->orderBy('name')->get()
            ->map(fn (Member $member): array => [
                'member' => $member,
                'assignments' => $service->eligibleAssignments($member)->when($scopeChurch !== null, fn ($items) => $items->where('membership.church_id', $scopeChurch->id)),
            ])
            ->filter(fn (array $item): bool => $item['assignments']->isNotEmpty())
            ->values();

        return view('users.create', compact('members'));
    }

    public function store(StoreUserRequest $request, UserService $service, PermissionService $permissions): View
    {
        $actor = $request->user();
        $scopeChurch = $actor->isGlobalAdministrator() ? null : $permissions->currentChurch($actor);
        [$user, $password] = $service->create(Member::query()->findOrFail($request->validated('member_id')), $request->validated('username'), $scopeChurch);

        return $this->showView($user, $service, $permissions, $password);
    }

    public function show(User $user, UserService $service, PermissionService $permissions): View
    {
        Gate::authorize('view', $user);

        return $this->showView($user, $service, $permissions);
    }

    public function activate(User $user, UserService $service): RedirectResponse
    {
        Gate::authorize('activate', $user);
        $service->changeStatus($user, Status::Active);

        return back()->with('success', 'Usuário ativado.');
    }

    public function inactivate(User $user, UserService $service): RedirectResponse
    {
        Gate::authorize('inactivate', $user);
        $service->changeStatus($user, Status::Inactive);

        return back()->with('success', 'Usuário inativado.');
    }

    public function resetPassword(User $user, UserService $service, PermissionService $permissions): View
    {
        Gate::authorize('resetPassword', $user);

        return $this->showView($user, $service, $permissions, $service->resetPassword($user));
    }

    public function forcePasswordChange(User $user, UserService $service): RedirectResponse
    {
        Gate::authorize('forcePasswordChange', $user);
        $service->requirePasswordChange($user);

        return back()->with('success', 'A troca de senha será exigida no próximo acesso.');
    }

    private function showView(User $user, UserService $service, PermissionService $permissions, ?string $temporaryPassword = null): View
    {
        $user->load('member');
        $actor = request()->user();
        $scopeChurch = $actor->isGlobalAdministrator() ? null : $permissions->currentChurch($actor);
        $assignments = $user->member ? $service->eligibleAssignments($user->member) : collect();
        if ($scopeChurch !== null) {
            $assignments = $assignments->where('membership.church_id', $scopeChurch->id)->values();
        }
        $churches = $assignments->pluck('membership.church')->filter()->unique('id')->values();
        $effectivePermissions = $churches->mapWithKeys(fn ($church): array => [
            $church->id => $permissions->effectivePermissions($user, $church),
        ]);

        return view('users.show', compact('user', 'assignments', 'churches', 'effectivePermissions', 'temporaryPassword'));
    }
}
