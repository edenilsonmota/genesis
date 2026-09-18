<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\PermissionLevel;
use App\Services\Finance\FinancialOverviewService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancialOverviewController extends Controller
{
    public function __invoke(Request $request, PermissionService $permissions, FinancialOverviewService $overview): View
    {
        $actor = $request->user();
        $church = $permissions->currentChurch($actor);
        abort_unless($actor->isGlobalAdministrator() || ($church !== null && $permissions->can($actor, 'finance.overview', PermissionLevel::Read, $church)), 403);

        $year = $request->integer('year', today()->year);
        if ($year < today()->year - 10 || $year > today()->year) {
            $year = today()->year;
        }

        return view('finance.overview.index', [
            'overview' => $overview->forScope($church, $year),
            'year' => $year,
            'isConsolidated' => $church === null,
        ]);
    }
}
