<?php

namespace App\Http\Controllers;

use App\Http\Requests\Organization\IndexOrganizationRequest;
use App\Models\Area;
use App\Models\Church;
use App\Models\City;
use App\Models\State;
use App\Services\PermissionService;
use App\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(IndexOrganizationRequest $request, PermissionService $permissions): View
    {
        $filters = $request->safe()->only(['search', 'status', 'state_id', 'city_id', 'panel', 'church']);
        $currentChurch = $permissions->currentChurch($request->user());

        $area = Area::query()
            ->withCount([
                'churches',
                'churches as active_churches_count' => fn (Builder $query): Builder => $query
                    ->where('status', Status::Active->value),
            ])
            ->first();

        $churches = Church::query()
            ->with(['city.state'])
            ->when(! $request->user()->isGlobalAdministrator(), fn (Builder $query): Builder => $query->whereKey($currentChurch?->id))
            ->when(
                $area === null,
                fn (Builder $query): Builder => $query->whereRaw('false'),
                fn (Builder $query): Builder => $query->whereBelongsTo($area),
            )
            ->when($filters['search'] ?? null, fn (Builder $query, string $search): Builder => $query
                ->where('name', 'ILIKE', "%{$search}%"))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query
                ->where('status', $status))
            ->when($filters['state_id'] ?? null, fn (Builder $query, int|string $stateId): Builder => $query
                ->whereHas('city', fn (Builder $cityQuery): Builder => $cityQuery->where('state_id', $stateId)))
            ->when($filters['city_id'] ?? null, fn (Builder $query, int|string $cityId): Builder => $query
                ->where('city_id', $cityId))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $states = State::query()->orderBy('name')->get();
        $filterCities = isset($filters['state_id'])
            ? City::query()->where('state_id', $filters['state_id'])->orderBy('name')->get()
            : collect();

        $selectedChurch = null;

        if ($area !== null && isset($filters['church'])) {
            $selectedChurch = Church::query()
                ->with(['city.state'])
                ->whereBelongsTo($area)
                ->when(! $request->user()->isGlobalAdministrator(), fn (Builder $query): Builder => $query->whereKey($currentChurch?->id))
                ->findOrFail($filters['church']);
        }

        $formStateId = $request->old('state_id')
            ?? $selectedChurch?->city?->state_id;
        $formCities = $formStateId
            ? City::query()->where('state_id', $formStateId)->orderBy('name')->get()
            : collect();

        return view('organization.index', [
            'area' => $area,
            'churches' => $churches,
            'filterCities' => $filterCities,
            'filters' => $filters,
            'formCities' => $formCities,
            'selectedChurch' => $selectedChurch,
            'states' => $states,
        ]);
    }
}
