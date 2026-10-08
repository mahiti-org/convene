<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\StoreActivityRequest;
use App\Http\Requests\Activity\UpdateActivityRequest;
use App\Http\Requests\DeactivateRequest;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    use ResolvesRequestChannel;

    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Activity, PermissionAction::View, $channel]);

        $query = Activity::query()->with(['beneficiaryType']);

        if ($request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('parent_activity_id')) {
            $query->where('parent_activity_id', $request->integer('parent_activity_id'));
        } elseif ($request->boolean('top_level_only')) {
            $query->whereNull('parent_activity_id');
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(StoreActivityRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Activity, PermissionAction::Create, $channel]);

        $activity = Activity::create($request->validated());

        return response()->json(['data' => $activity], 201);
    }

    public function update(UpdateActivityRequest $request, Activity $activity): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Activity, PermissionAction::Edit, $channel]);

        $activity->update($request->validated());

        return response()->json(['data' => $activity]);
    }

    public function deactivate(DeactivateRequest $request, Activity $activity): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Activity, PermissionAction::SoftDelete, $channel]);

        $activity->deactivate($request->validated('reason'), $request->user());

        return response()->json(['data' => $activity->fresh()]);
    }
}
