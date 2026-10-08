<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Flattened permission tuples and project contexts from the user's active Grants.
     */
    public function effectivePermissions(Request $request): JsonResponse
    {
        $user = $request->user();

        $permissions = $user->activeGrants()
            ->with('role.permissions')
            ->get()
            ->flatMap(fn ($grant) => $grant->role->permissions)
            ->unique('id')
            ->map(fn ($permission) => [
                'resourceType' => $permission->resource_type->value,
                'action' => $permission->action->value,
                'channel' => $permission->channel->value,
            ])
            ->values();

        $projectContexts = $user->activeGrants()
            ->whereNotNull('project_id')
            ->get()
            ->pluck('project_id')
            ->unique()
            ->map(fn ($projectId) => ['projectId' => (string) $projectId])
            ->values();

        return response()->json([
            'permissions' => $permissions,
            'projectContexts' => $projectContexts,
        ]);
    }
}
