<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateRequest;
use App\Http\Requests\SubProject\StoreSubProjectRequest;
use App\Http\Requests\SubProject\UpdateSubProjectRequest;
use App\Models\SubProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// Authorized under ResourceType::Project.
class SubProjectController extends Controller
{
    use ResolvesRequestChannel;

    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::View, $channel]);

        $query = SubProject::query();

        if ($request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(StoreSubProjectRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::Create, $channel]);

        $subProject = SubProject::create($request->validated());

        return response()->json(['data' => $subProject], 201);
    }

    public function update(UpdateSubProjectRequest $request, SubProject $subProject): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::Edit, $channel]);

        $subProject->update($request->validated());

        return response()->json(['data' => $subProject]);
    }

    public function deactivate(DeactivateRequest $request, SubProject $subProject): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::SoftDelete, $channel]);

        $subProject->deactivate($request->validated('reason'), $request->user());

        return response()->json(['data' => $subProject->fresh()]);
    }
}
