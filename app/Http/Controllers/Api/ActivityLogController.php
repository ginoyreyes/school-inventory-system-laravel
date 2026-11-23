<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('logs:read');
        $limit = min(100, max(1, (int) $request->query('limit', 20)));

        $query = ActivityLog::query();
        if ($request->filled('from')) $query->where('created_at', '>=', Carbon::parse($request->query('from'))->startOfDay());
        if ($request->filled('to')) $query->where('created_at', '<=', Carbon::parse($request->query('to'))->endOfDay());
        if ($request->filled('search')) {
            $s = '%' . $request->query('search') . '%';
            $query->where(fn ($q) => $q->where('action', 'like', $s)->orWhere('actor_name', 'like', $s)->orWhere('actor_username', 'like', $s)->orWhere('entity_label', 'like', $s)->orWhere('details', 'like', $s));
        }

        $actionRows = ActivityLog::selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->orderByDesc('count')
            ->limit(12)
            ->get()
            ->map(fn ($r) => ['action' => $r->action, 'count' => (int) $r->count]);

        $paginated = $query->orderByDesc('created_at')->orderByDesc('id')->paginate($limit);

        $logs = collect($paginated->items())->map(fn ($log) => [
            'id'            => (string) $log->id,
            'actorName'     => $log->actor_name,
            'actorUsername' => $log->actor_username,
            'actorRole'     => $log->actor_role,
            'action'        => $log->action,
            'entityType'    => $log->entity_type,
            'entityId'      => $log->entity_id,
            'entityLabel'   => $log->entity_label,
            'details'       => $log->details,
            'createdAt'     => $log->created_at?->toISOString(),
        ]);

        return response()->json([
            'logs'    => $logs,
            'total'   => $paginated->total(),
            'page'    => $paginated->currentPage(),
            'pages'   => max(1, $paginated->lastPage()),
            'actions' => $actionRows,
        ]);
    }
}