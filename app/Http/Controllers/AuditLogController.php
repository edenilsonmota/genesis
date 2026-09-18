<?php

namespace App\Http\Controllers;

use App\Http\Requests\Audit\IndexAuditLogRequest;
use App\Models\Area;
use App\Models\Church;
use App\Services\AuditLogQueryService;
use App\Services\PermissionService;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(
        IndexAuditLogRequest $request,
        PermissionService $permissions,
        AuditLogQueryService $auditLogs,
    ): View {
        $filters = $request->validated();
        $church = $permissions->currentChurch($request->user());
        $scopeQuery = $auditLogs->forScope($church);

        $actions = (clone $scopeQuery)->select('action')->distinct()->orderBy('action')->pluck('action');
        $resources = (clone $scopeQuery)->select('resource')->distinct()->orderBy('resource')->pluck('resource');
        $filtered = $auditLogs->filtered($church, $filters);
        $logs = (clone $filtered)->latest()->paginate(20)->withQueryString();
        $scopeIds = $logs->getCollection()->pluck('scope_id')->filter()->unique()->values();
        $scopeLabels = Church::query()->whereKey($scopeIds)->pluck('name', 'id')
            ->merge(Area::query()->whereKey($scopeIds)->pluck('name', 'id'));

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'church' => $church,
            'actions' => $actions,
            'resources' => $resources,
            'actionLabels' => $auditLogs->actionLabels(),
            'resourceLabels' => $auditLogs->resourceLabels(),
            'scopeLabels' => $scopeLabels,
            'summary' => [
                'records' => (clone $filtered)->count(),
                'today' => (clone $scopeQuery)->whereDate('created_at', today())->count(),
                'actors' => (clone $scopeQuery)->whereNotNull('actor_user_id')->distinct('actor_user_id')->count('actor_user_id'),
                'resources' => (clone $scopeQuery)->distinct('resource')->count('resource'),
            ],
        ]);
    }
}
