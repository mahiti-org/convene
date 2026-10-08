<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeactivateRequest;
use App\Http\Requests\FormDefinition\StoreFormDefinitionRequest;
use App\Http\Requests\FormDefinition\UpdateFormDefinitionRequest;
use App\Models\FormDefinition;
use App\Models\FormDefinitionAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class FormDefinitionController extends Controller
{
    use ResolvesRequestChannel;

    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::FormDefinition, PermissionAction::View, $channel]);

        $query = FormDefinition::query()->with(['beneficiaryType', 'activity']);

        if ($request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        if ($request->filled('code')) {
            $query->where('code', $request->string('code')->value());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        } else {
            // Default view: only the latest version per code that isn't deprecated.
            $query->where('status', '!=', FormDefinition::STATUS_DEPRECATED);
        }

        return response()->json(['data' => $query->orderBy('code')->orderByDesc('version')->get()]);
    }

    public function store(StoreFormDefinitionRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::FormDefinition, PermissionAction::Create, $channel]);

        $form = FormDefinition::create([...$request->validated(), 'created_by' => $request->user()?->id]);

        return response()->json(['data' => $form], 201);
    }

    /**
     * Drafts mutate in place; a published row is never mutated, changes go to a new draft version.
     */
    public function update(UpdateFormDefinitionRequest $request, FormDefinition $formDefinition): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::FormDefinition, PermissionAction::Edit, $channel]);

        $target = $formDefinition->isPublished()
            ? $formDefinition->createNewDraftVersionFromPublished()
            : $formDefinition;

        $target->update($request->validated());

        return response()->json(['data' => $target, 'created_new_version' => $target->id !== $formDefinition->id]);
    }

    public function publish(Request $request, FormDefinition $formDefinition): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::FormDefinition, PermissionAction::Approve, $channel]);

        try {
            $formDefinition->publish();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $formDefinition->fresh()]);
    }

    public function deactivate(DeactivateRequest $request, FormDefinition $formDefinition): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::FormDefinition, PermissionAction::SoftDelete, $channel]);

        $formDefinition->deactivate($request->validated('reason'), $request->user());

        return response()->json(['data' => $formDefinition->fresh()]);
    }

    /** Assigns a form by its stable code to a user or a role. */
    public function assign(Request $request, string $formCode): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::FormDefinition, PermissionAction::Assign, $channel]);

        $request->validate([
            'assignable_type' => ['required', 'in:user,role'],
            'assignable_id' => ['required', 'integer'],
        ]);

        $assignment = FormDefinitionAssignment::query()->firstOrCreate([
            'form_code' => $formCode,
            'assignable_type' => $request->string('assignable_type')->value(),
            'assignable_id' => $request->integer('assignable_id'),
        ]);

        return response()->json(['data' => $assignment], 201);
    }

    public function unassign(Request $request, string $formCode): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::FormDefinition, PermissionAction::Assign, $channel]);

        $request->validate([
            'assignable_type' => ['required', 'in:user,role'],
            'assignable_id' => ['required', 'integer'],
        ]);

        FormDefinitionAssignment::query()
            ->where('form_code', $formCode)
            ->where('assignable_type', $request->string('assignable_type')->value())
            ->where('assignable_id', $request->integer('assignable_id'))
            ->delete();

        return response()->json(['data' => null]);
    }
}
