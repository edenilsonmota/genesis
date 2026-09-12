<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FinancialCategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinancialCategory\IndexFinancialCategoryRequest;
use App\Http\Requests\Finance\FinancialCategory\StoreFinancialCategoryRequest;
use App\Http\Requests\Finance\FinancialCategory\UpdateFinancialCategoryRequest;
use App\Models\Area;
use App\Models\FinancialAccount;
use App\Models\FinancialCategory;
use App\Services\Finance\FinancialCategoryService;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class FinancialCategoryController extends Controller
{
    public function index(IndexFinancialCategoryRequest $request, PermissionService $permissions): View
    {
        $filters = $request->validated();
        $actor = $request->user();
        $currentChurch = $actor->isGlobalAdministrator() ? null : $permissions->currentChurch($actor);
        $area = $actor->isGlobalAdministrator()
            ? Area::query()->first()
            : Area::query()->find($currentChurch?->area_id);

        $categories = FinancialCategory::query()
            ->with('area:id,name')
            ->when($area === null, fn (Builder $query): Builder => $query->whereRaw('false'))
            ->when($area !== null, fn (Builder $query): Builder => $query->whereBelongsTo($area))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%")))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type): Builder => $query->ofType($type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('finance.categories.index', [
            'area' => $area,
            'categories' => $categories,
            'categoryTypes' => FinancialCategoryType::cases(),
            'filters' => $filters,
            'canViewFinancialAccounts' => Gate::allows('viewAny', FinancialAccount::class),
            'canViewFinancialCategories' => true,
            'canWriteFinancialCategories' => Gate::allows('create', FinancialCategory::class),
        ]);
    }

    public function create(PermissionService $permissions): View|RedirectResponse
    {
        Gate::authorize('create', FinancialCategory::class);
        $area = $this->currentArea($permissions);
        if ($area === null) {
            $globalAdministrator = request()->user()->isGlobalAdministrator();
            $route = $globalAdministrator
                ? route('organization.index', ['panel' => 'create-area'])
                : route('finance.categories.index');

            return redirect($route)->withErrors(['area' => 'Cadastre e ative a área antes de criar categorias financeiras.']);
        }

        return view('finance.categories.create', [
            'area' => $area,
            'categoryTypes' => FinancialCategoryType::cases(),
        ]);
    }

    public function store(StoreFinancialCategoryRequest $request, FinancialCategoryService $service): RedirectResponse
    {
        $service->create($request->validated());

        return redirect()->route('finance.categories.index')->with('success', 'Categoria financeira criada com sucesso.');
    }

    public function edit(FinancialCategory $financialCategory, PermissionService $permissions): View
    {
        Gate::authorize('update', $financialCategory);

        return view('finance.categories.edit', [
            'area' => $this->currentArea($permissions),
            'categoryTypes' => FinancialCategoryType::cases(),
            'financialCategory' => $financialCategory,
        ]);
    }

    public function update(UpdateFinancialCategoryRequest $request, FinancialCategory $financialCategory, FinancialCategoryService $service): RedirectResponse
    {
        $service->update($financialCategory, $request->validated());

        return redirect()->route('finance.categories.index')->with('success', 'Categoria financeira atualizada com sucesso.');
    }

    public function activate(FinancialCategory $financialCategory, FinancialCategoryService $service): RedirectResponse
    {
        Gate::authorize('update', $financialCategory);
        $service->changeStatus($financialCategory, Status::Active);

        return back()->with('success', 'Categoria financeira ativada.');
    }

    public function inactivate(FinancialCategory $financialCategory, FinancialCategoryService $service): RedirectResponse
    {
        Gate::authorize('update', $financialCategory);
        $service->changeStatus($financialCategory, Status::Inactive);

        return back()->with('success', 'Categoria financeira inativada.');
    }

    private function currentArea(PermissionService $permissions): ?Area
    {
        $actor = request()->user();
        $area = $actor->isGlobalAdministrator()
            ? Area::query()->where('status', Status::Active->value)->first()
            : $permissions->currentChurch($actor)?->area()->where('status', Status::Active->value)->first();

        return $area;
    }
}
