<?php

namespace App\Http\Controllers;

use App\Http\Requests\Department\IndexDepartmentRequest;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(IndexDepartmentRequest $request): View
    {
        $filters = $request->validated();
        $departments = Department::query()
            ->select('departments.*')
            ->selectSub(fn ($query) => $query->from('positions')
                ->whereColumn('positions.department_id', 'departments.id')
                ->selectRaw('COUNT(*)'), 'positions_count')
            ->selectSub(fn ($query) => $query->from('member_position_assignments as assignments')
                ->join('positions', 'positions.id', '=', 'assignments.position_id')
                ->join('member_church_memberships as memberships', 'memberships.id', '=', 'assignments.member_church_membership_id')
                ->whereColumn('positions.department_id', 'departments.id')
                ->selectRaw('COUNT(DISTINCT memberships.member_id)'), 'members_count')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query
                ->where(fn (Builder $query): Builder => $query->where('name', 'ILIKE', "%{$search}%")->orWhere('description', 'ILIKE', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->orderBy('name')->orderBy('id')->paginate(10)->withQueryString();

        return view('departments.index', compact('departments', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('create', Department::class);

        return view('departments.create');
    }

    public function store(StoreDepartmentRequest $request, DepartmentService $service): RedirectResponse|JsonResponse
    {
        $department = $service->create($request->safe()->only(['name', 'description']));

        if ($request->expectsJson()) {
            return response()->json([
                'department' => $department->only(['id', 'name']),
            ], 201);
        }

        return redirect()->route('departments.edit', $department)->with('success', 'Departamento criado com sucesso.');
    }

    public function edit(Department $department): View
    {
        Gate::authorize('update', $department);

        return view('departments.edit', compact('department'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department, DepartmentService $service): RedirectResponse
    {
        $service->update($department, $request->safe()->only(['name', 'description']));

        return redirect()->route('departments.index')->with('success', 'Departamento atualizado com sucesso.');
    }

    public function status(Request $request, Department $department, DepartmentService $service): RedirectResponse
    {
        Gate::authorize('update', $department);
        $status = Status::from($request->validate(['status' => ['required', 'in:active,inactive']])['status']);
        $service->changeStatus($department, $status);

        return back()->with('success', 'Status do departamento atualizado.');
    }
}
