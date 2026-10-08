<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterDefinition\DeactivateMasterDefinitionRequest;
use App\Http\Requests\MasterDefinition\StoreMasterDefinitionRequest;
use App\Http\Requests\MasterDefinition\UpdateMasterDefinitionRequest;
use App\Imports\MasterDefinitionImport;
use App\Models\MasterDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class MasterDefinitionController extends Controller
{
    use ResolvesRequestChannel;

    /** List master look-ups, optionally filtered to one category (e.g. ?category=gender). */
    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Master, PermissionAction::View, $channel]);

        $query = MasterDefinition::query();

        if ($request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        if ($request->filled('category')) {
            $query->category($request->string('category')->value());
        }

        return response()->json([
            'data' => $query->orderBy('category')->orderBy('sort_order')->orderBy('label')->get(),
        ]);
    }

    public function store(StoreMasterDefinitionRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Master, PermissionAction::Create, $channel]);

        $master = MasterDefinition::create($request->validated());

        return response()->json(['data' => $master], 201);
    }

    public function update(UpdateMasterDefinitionRequest $request, MasterDefinition $masterDefinition): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Master, PermissionAction::Edit, $channel]);

        $masterDefinition->update($request->validated());

        return response()->json(['data' => $masterDefinition]);
    }

    public function deactivate(DeactivateMasterDefinitionRequest $request, MasterDefinition $masterDefinition): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Master, PermissionAction::SoftDelete, $channel]);

        $masterDefinition->deactivate($request->validated('reason'), $request->user());

        return response()->json(['data' => $masterDefinition->fresh()]);
    }

    /**
     * Validates all rows first; any invalid row returns the error report and persists nothing.
     */
    public function import(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Master, PermissionAction::Import, $channel]);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv']]);

        $import = new MasterDefinitionImport;
        Excel::import($import, $request->file('file'));

        if (! empty($import->errors())) {
            return response()->json(['errors' => $import->errors()], 422);
        }

        return response()->json(['created' => $import->createdCount()]);
    }
}
