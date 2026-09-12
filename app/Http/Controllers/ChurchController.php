<?php

namespace App\Http\Controllers;

use App\Http\Requests\Church\StoreChurchRequest;
use App\Http\Requests\Church\UpdateChurchRequest;
use App\Models\Church;
use App\Services\ChurchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ChurchController extends Controller
{
    public function store(StoreChurchRequest $request, ChurchService $service): RedirectResponse
    {
        $service->create($request->safe()->only([
            'city_id',
            'name',
            'postal_code',
            'street',
            'neighborhood',
            'number',
            'complement',
            'status',
        ]));

        return redirect()
            ->route('organization.index')
            ->with('success', 'Igreja cadastrada com sucesso.');
    }

    public function update(UpdateChurchRequest $request, Church $church, ChurchService $service): RedirectResponse
    {
        $service->update($church, $request->safe()->only([
            'city_id',
            'name',
            'postal_code',
            'street',
            'neighborhood',
            'number',
            'complement',
            'status',
        ]));

        return redirect()
            ->route('organization.index')
            ->with('success', 'Igreja atualizada com sucesso.');
    }

    public function inactivate(Request $request, Church $church, ChurchService $service): RedirectResponse
    {
        Gate::authorize('inactivate', $church);
        $service->inactivate($church);

        return redirect()
            ->route('organization.index', $request->only(['search', 'status', 'state_id', 'city_id', 'page']))
            ->with('success', 'Igreja inativada com sucesso.');
    }
}
