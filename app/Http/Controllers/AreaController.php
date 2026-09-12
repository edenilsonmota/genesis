<?php

namespace App\Http\Controllers;

use App\Http\Requests\Area\StoreAreaRequest;
use App\Http\Requests\Area\UpdateAreaRequest;
use App\Models\Area;
use App\Services\AreaService;
use Illuminate\Http\RedirectResponse;

class AreaController extends Controller
{
    public function store(StoreAreaRequest $request, AreaService $service): RedirectResponse
    {
        $service->create($request->safe()->only(['name', 'description', 'status']));

        return redirect()
            ->route('organization.index')
            ->with('success', 'Área cadastrada com sucesso.');
    }

    public function update(UpdateAreaRequest $request, Area $area, AreaService $service): RedirectResponse
    {
        $service->update($area, $request->safe()->only(['name', 'description', 'status']));

        return redirect()
            ->route('organization.index')
            ->with('success', 'Área atualizada com sucesso.');
    }
}
