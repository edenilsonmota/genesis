<?php

namespace App\Http\Controllers;

use App\PermissionLevel;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, PermissionService $permissions): View
    {
        abort_unless($permissions->can($request->user(), 'dashboard', PermissionLevel::Read), 403);

        return view('dashboard.index');
    }
}
