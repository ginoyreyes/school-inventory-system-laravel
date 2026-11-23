<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

trait LogsActivity
{
    protected function logActivity(Request $request, string $action, string $entityType = '', string $entityId = '', string $entityLabel = '', string $details = ''): void
    {
        $user = $request->user();

        ActivityLog::create([
            'actor_id'       => $user?->id,
            'actor_name'     => $user?->name ?? 'System',
            'actor_username' => $user?->username ?? '',
            'actor_role'     => $user?->role ?? '',
            'action'         => $action,
            'entity_type'    => $entityType,
            'entity_id'      => $entityId,
            'entity_label'   => $entityLabel,
            'details'        => $details,
            'ip'             => $request->ip() ?? '',
            'user_agent'     => $request->userAgent() ?? '',
        ]);
    }
}