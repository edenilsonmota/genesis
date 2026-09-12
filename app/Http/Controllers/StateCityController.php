<?php

namespace App\Http\Controllers;

use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StateCityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(State $state): JsonResponse
    {
        Gate::authorize('view-localities');

        return response()->json([
            'data' => $state->cities()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
