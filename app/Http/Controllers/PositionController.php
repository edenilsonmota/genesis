<?php

namespace App\Http\Controllers;

use App\Http\Requests\Position\IndexPositionRequest;
use App\Http\Requests\Position\StorePositionRequest;
use App\Http\Requests\Position\UpdatePositionPermissionsRequest;
use App\Http\Requests\Position\UpdatePositionRequest;
use App\Models\Department;
use App\Models\PermissionModule;
use App\Models\Position;
use App\Services\PositionService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(IndexPositionRequest $request): View
    {
        $filters = $request->validated();
        $positions = Position::query()->with('department')->withCount('assignments')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query
                ->where(fn (Builder $query): Builder => $query->where('name', 'ILIKE', "%{$search}%")->orWhere('description', 'ILIKE', "%{$search}%")))
            ->when($filters['department_id'] ?? null, fn (Builder $query, string $id): Builder => $query->where('department_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when(isset($filters['grants_system_access']), fn (Builder $query): Builder => $query->where('grants_system_access', $filters['grants_system_access'] === '1'))
            ->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();

        return view('positions.index', [
            'positions' => $positions,
            'filters' => $filters,
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Position::class);

        return view('positions.create', ['departments' => Department::query()->active()->orderBy('name')->get()]);
    }

    public function store(StorePositionRequest $request, PositionService $service): RedirectResponse
    {
        $position = $service->create($request->validated());

        return redirect()->route('positions.edit', $position)->with('success', 'Cargo criado com sucesso.');
    }

    public function edit(Position $position, PositionService $service): View
    {
        Gate::authorize('update', $position);

        return view('positions.edit', [
            'position' => $position,
            'departments' => Department::query()->active()->orWhereKey($position->department_id)->orderBy('name')->get(),
            'impact' => $service->impact($position),
        ]);
    }

    public function update(UpdatePositionRequest $request, Position $position, PositionService $service): RedirectResponse
    {
        $service->update($position, $request->validated());

        return redirect()->route('positions.index')->with('success', 'Cargo atualizado com sucesso.');
    }

    public function status(Request $request, Position $position, PositionService $service): RedirectResponse
    {
        Gate::authorize('update', $position);
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive'],
            'confirm_access_revocation' => ['sometimes', 'boolean'],
        ]);
        $service->changeStatus($position, Status::from($validated['status']), (bool) ($validated['confirm_access_revocation'] ?? false));

        return back()->with('success', 'Status do cargo atualizado.');
    }

    public function permissions(Position $position, PositionService $service): View
    {
        Gate::authorize('updatePermissions', $position);
        $position->load('permissions');
        $modules = PermissionModule::query()->where('status', Status::Active->value)->orderByRaw("CASE category WHEN 'Principal' THEN 1 WHEN 'Cadastros' THEN 2 WHEN 'Administração' THEN 3 WHEN 'Financeiro' THEN 4 ELSE 5 END")->orderBy('name')->get()->groupBy('category');

        return view('positions.permissions', [
            'position' => $position,
            'modulesByCategory' => $modules,
            'levels' => $position->permissions->pluck('level.value', 'permission_module_id'),
            'impact' => $service->impact($position),
        ]);
    }

    public function updatePermissions(UpdatePositionPermissionsRequest $request, Position $position, PositionService $service): RedirectResponse
    {
        $service->syncPermissions($position, $request->validated('permissions'), $request->boolean('confirm_access_revocation'));

        return back()->with('success', 'Matriz de permissões atualizada com sucesso.');
    }
}
