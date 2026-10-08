<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\GeographyNode;
use App\Models\Project;
use App\Models\ProjectLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    use ResolvesRequestChannel;

    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::View, $channel]);

        $query = Project::query()->with(['program', 'projectManager']);

        if ($request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        if ($request->filled('program_id')) {
            $query->where('program_id', $request->integer('program_id'));
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::Create, $channel]);

        $project = Project::create($request->validated());

        return response()->json(['data' => $project], 201);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::Edit, $channel]);

        $project->update($request->validated());

        return response()->json(['data' => $project]);
    }

    public function deactivate(DeactivateRequest $request, Project $project): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::SoftDelete, $channel]);

        $project->deactivate($request->validated('reason'), $request->user());

        return response()->json(['data' => $project->fresh()]);
    }

    /**
     * Assigns working locations; this also changes who derived-scope Grants cover.
     */
    public function attachLocation(Request $request, Project $project): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::Assign, $channel]);

        $request->validate(['geography_node_id' => ['required', 'integer', 'exists:geography_nodes,id']]);

        $projectLocation = ProjectLocation::query()->firstOrCreate([
            'project_id' => $project->id,
            'geography_node_id' => $request->integer('geography_node_id'),
        ]);

        return response()->json(['data' => $projectLocation], 201);
    }

    public function detachLocation(Request $request, Project $project, GeographyNode $geographyNode): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Project, PermissionAction::Assign, $channel]);

        // Instance-level delete (not a bulk query ->delete()) so the ProjectLocation::booted()
        // `deleted` event fires and triggers derived-scope Grant recomputation.
        ProjectLocation::query()
            ->where('project_id', $project->id)
            ->where('geography_node_id', $geographyNode->id)
            ->get()
            ->each(fn (ProjectLocation $projectLocation) => $projectLocation->delete());

        return response()->json(['data' => null]);
    }
}
