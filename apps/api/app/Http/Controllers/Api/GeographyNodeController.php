<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeographyNode\DeactivateGeographyNodeRequest;
use App\Http\Requests\GeographyNode\StoreGeographyNodeRequest;
use App\Http\Requests\GeographyNode\UpdateGeographyNodeRequest;
use App\Imports\GeographyNodeImport;
use App\Models\GeographyNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class GeographyNodeController extends Controller
{
    use ResolvesRequestChannel;

    /**
     * List/search geography nodes. Filters: parent_id, level, search (label prefix/contains),
     * include_inactive (admin-only view of deactivated nodes).
     */
    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::GeographyNode, PermissionAction::View, $channel]);

        $query = GeographyNode::query();

        if ($request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->integer('parent_id'));
        } elseif ($request->boolean('roots_only')) {
            $query->whereNull('parent_id');
        }

        if ($request->filled('level')) {
            $query->where('level', $request->integer('level'));
        }

        if ($request->filled('search')) {
            $query->where('label', 'like', '%'.$request->string('search')->value().'%');
        }

        return response()->json([
            'data' => $query->orderBy('label')->get(),
        ]);
    }

    /** Full hierarchy as a nested tree, used by the cascading-select widget and web admin tree UI. */
    public function tree(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::GeographyNode, PermissionAction::View, $channel]);

        $nodes = GeographyNode::query()->orderBy('level')->orderBy('label')->get();

        /** @var array<int|string, array<int, GeographyNode>> $byParent */
        $byParent = $nodes->groupBy('parent_id')->map(fn ($group) => $group->all())->all();

        return response()->json(['data' => $this->buildTree(null, $byParent)]);
    }

    /**
     * @param  array<int|string, array<int, GeographyNode>>  $byParent
     * @return array<int, array{id: int, label: string, code: string|null, level: int, children: array}>
     */
    private function buildTree(?int $parentId, array $byParent): array
    {
        // Eloquent's groupBy() casts a null grouping key to '' as the array key.
        $siblings = $byParent[$parentId ?? ''] ?? [];

        return array_values(array_map(fn (GeographyNode $node) => [
            'id' => $node->id,
            'label' => $node->label,
            'code' => $node->code,
            'level' => $node->level,
            'children' => $this->buildTree($node->id, $byParent),
        ], $siblings));
    }

    public function store(StoreGeographyNodeRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::GeographyNode, PermissionAction::Create, $channel]);

        $node = GeographyNode::create($request->validated());

        return response()->json(['data' => $node], 201);
    }

    // Only label/code edits here; re-parenting would require recomputing location_set_members for
    // every affected Grant.
    public function update(UpdateGeographyNodeRequest $request, GeographyNode $geographyNode): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [
            ResourceType::GeographyNode, PermissionAction::Edit, $channel, null, $geographyNode->id,
        ]);

        $geographyNode->update($request->validated());

        return response()->json(['data' => $geographyNode]);
    }

    public function deactivate(DeactivateGeographyNodeRequest $request, GeographyNode $geographyNode): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [
            ResourceType::GeographyNode, PermissionAction::SoftDelete, $channel, null, $geographyNode->id,
        ]);

        $geographyNode->deactivate($request->validated('reason'), $request->user());

        return response()->json(['data' => $geographyNode->fresh()]);
    }

    /**
     * Validates all rows first; any invalid row returns the error report and persists nothing.
     */
    public function import(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::GeographyNode, PermissionAction::Import, $channel]);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $import = new GeographyNodeImport;
        Excel::import($import, $request->file('file'));

        if (! empty($import->errors())) {
            return response()->json(['errors' => $import->errors()], 422);
        }

        return response()->json(['created' => $import->createdCount()]);
    }
}
