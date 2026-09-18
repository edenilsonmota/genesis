<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActiveChurchController extends Controller
{
    public function update(Request $request, PermissionService $permissions): RedirectResponse
    {
        if ($request->user()->isGlobalAdministrator() && $request->input('church_id') === '__overview__') {
            $request->session()->put('active_church_id', '__overview__');

            return back()->with('success', 'Área selecionada.');
        }

        $validated = $request->validate(['church_id' => ['required', 'uuid', 'exists:churches,id']]);
        $church = Church::query()->findOrFail($validated['church_id']);

        abort_unless($permissions->canAccessChurch($request->user(), $church), 404);
        $request->session()->put('active_church_id', $church->id);

        return back()->with('success', "Igreja ativa alterada para {$church->name}.");
    }
}
