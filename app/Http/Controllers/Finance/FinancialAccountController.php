<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FinancialAccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinancialAccount\IndexFinancialAccountRequest;
use App\Http\Requests\Finance\FinancialAccount\StoreFinancialAccountRequest;
use App\Http\Requests\Finance\FinancialAccount\UpdateFinancialAccountRequest;
use App\Models\Area;
use App\Models\Church;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Services\Finance\FinancialAccountService;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinancialAccountController extends Controller
{
    public function index(IndexFinancialAccountRequest $request, PermissionService $permissions): View
    {
        $filters = $request->validated();
        $actor = $request->user();
        $currentChurch = $actor->isGlobalAdministrator() ? null : $permissions->currentChurch($actor);

        $accounts = FinancialAccount::query()
            ->with(['area:id,name', 'church:id,name,area_id'])
            ->when(! $actor->isGlobalAdministrator(), fn (Builder $query): Builder => $currentChurch === null
                ? $query->whereRaw('false')
                : $query->forChurch($currentChurch->id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('institution', 'ILIKE', "%{$search}%")))
            ->when($filters['owner_type'] ?? null, fn (Builder $query, string $ownerType): Builder => $ownerType === 'area'
                ? $query->whereNotNull('area_id')
                : $query->whereNotNull('church_id'))
            ->when($filters['owner_id'] ?? null, fn (Builder $query, string $ownerId): Builder => $query
                ->where(fn (Builder $query): Builder => $query->where('area_id', $ownerId)->orWhere('church_id', $ownerId)))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type): Builder => $query->ofType($type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        [$area, $churches] = $this->availableOwners($actor->isGlobalAdministrator(), $currentChurch);

        return view('finance.accounts.index', [
            'accounts' => $accounts,
            'accountTypes' => FinancialAccountType::cases(),
            'area' => $area,
            'churches' => $churches,
            'filters' => $filters,
            'canViewFinancialAccounts' => true,
            'canViewFinancialCategories' => Gate::allows('viewAny', FinancialCategory::class),
            'canWriteFinancialAccounts' => Gate::allows('create', FinancialAccount::class),
        ]);
    }

    public function create(PermissionService $permissions): View|RedirectResponse
    {
        Gate::authorize('create', FinancialAccount::class);
        $actor = request()->user();
        $currentChurch = $actor->isGlobalAdministrator() ? null : $permissions->currentChurch($actor);
        [$area, $churches] = $this->availableOwners($actor->isGlobalAdministrator(), $currentChurch, activeOnly: true);
        if ($area === null) {
            return $this->redirectToAreaPrerequisite($actor->isGlobalAdministrator(), 'Cadastre e ative a área antes de criar contas financeiras.');
        }

        return view('finance.accounts.create', [
            'accountTypes' => FinancialAccountType::cases(),
            'area' => $area,
            'churches' => $churches,
            'defaultOwnerType' => $actor->isGlobalAdministrator() ? 'area' : 'church',
        ]);
    }

    public function store(StoreFinancialAccountRequest $request, FinancialAccountService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user());

        return redirect()->route('finance.accounts.index')->with('success', 'Conta financeira criada com sucesso.');
    }

    public function edit(FinancialAccount $financialAccount, PermissionService $permissions): View
    {
        Gate::authorize('update', $financialAccount);
        $actor = request()->user();
        $currentChurch = $actor->isGlobalAdministrator() ? null : $permissions->currentChurch($actor);
        [$area, $churches] = $this->availableOwners($actor->isGlobalAdministrator(), $currentChurch, activeOnly: true);

        if ($financialAccount->church_id !== null && ! $churches->contains('id', $financialAccount->church_id)) {
            $churches->push(Church::query()->findOrFail($financialAccount->church_id));
        }

        return view('finance.accounts.edit', [
            'accountTypes' => FinancialAccountType::cases(),
            'area' => $area,
            'churches' => $churches->sortBy('name')->values(),
            'financialAccount' => $financialAccount,
            'defaultOwnerType' => $financialAccount->area_id !== null ? 'area' : 'church',
        ]);
    }

    public function update(UpdateFinancialAccountRequest $request, FinancialAccount $financialAccount, FinancialAccountService $service): RedirectResponse
    {
        $service->update($financialAccount, $request->validated(), $request->user());

        return redirect()->route('finance.accounts.index')->with('success', 'Conta financeira atualizada com sucesso.');
    }

    public function activate(FinancialAccount $financialAccount, FinancialAccountService $service): RedirectResponse
    {
        Gate::authorize('update', $financialAccount);
        $service->changeStatus($financialAccount, Status::Active);

        return back()->with('success', 'Conta financeira ativada.');
    }

    public function inactivate(FinancialAccount $financialAccount, FinancialAccountService $service): RedirectResponse
    {
        Gate::authorize('update', $financialAccount);
        $service->changeStatus($financialAccount, Status::Inactive);

        return back()->with('success', 'Conta financeira inativada.');
    }

    /** @return array{0: ?Area, 1: Collection<int, Church>} */
    private function availableOwners(bool $globalAdministrator, ?Church $currentChurch, bool $activeOnly = false): array
    {
        $area = Area::query()
            ->when($activeOnly, fn (Builder $query): Builder => $query->where('status', Status::Active->value))
            ->first();
        $churches = $globalAdministrator
            ? Church::query()->when($activeOnly, fn (Builder $query): Builder => $query->active())->orderBy('name')->get()
            : collect($currentChurch ? [$currentChurch] : []);

        return [$area, $churches];
    }

    private function redirectToAreaPrerequisite(bool $globalAdministrator, string $message): RedirectResponse
    {
        $route = $globalAdministrator
            ? route('organization.index', ['panel' => 'create-area'])
            : route('finance.accounts.index');

        return redirect($route)->withErrors(['area' => $message]);
    }
}
