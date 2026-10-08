<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateRequest;
use App\Http\Requests\Program\StoreProgramRequest;
use App\Http\Requests\Program\UpdateProgramRequest;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProgramController extends Controller
{
    use ResolvesRequestChannel;

    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Program, PermissionAction::View, $channel]);

        $query = Program::query()->with('programManager');

        if ($request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(StoreProgramRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Program, PermissionAction::Create, $channel]);

        $program = Program::create($request->validated());

        return response()->json(['data' => $program], 201);
    }

    public function update(UpdateProgramRequest $request, Program $program): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Program, PermissionAction::Edit, $channel]);

        $program->update($request->validated());

        return response()->json(['data' => $program]);
    }

    public function deactivate(DeactivateRequest $request, Program $program): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Program, PermissionAction::SoftDelete, $channel]);

        $program->deactivate($request->validated('reason'), $request->user());

        return response()->json(['data' => $program->fresh()]);
    }
}
