<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\FormResponse\StoreFormResponseRequest;
use App\Http\Requests\FormResponse\UpdateFormResponseRequest;
use App\Models\FormDefinition;
use App\Models\FormResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FormResponseController extends Controller
{
    use ResolvesRequestChannel;

    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::FormResponse, PermissionAction::View, $channel]);

        $query = FormResponse::query()->with(['formDefinition', 'beneficiary']);

        if ($request->filled('form_definition_id')) {
            $query->where('form_definition_id', $request->integer('form_definition_id'));
        }

        if ($request->filled('beneficiary_id')) {
            $query->where('beneficiary_id', $request->integer('beneficiary_id'));
        }

        return response()->json(['data' => $query->orderByDesc('submitted_at')->paginate($request->integer('per_page', 25))]);
    }

    /** Submissions may only be captured against a PUBLISHED form (never a draft). */
    public function store(StoreFormResponseRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        $data = $request->validated();

        $formDefinition = FormDefinition::query()->findOrFail($data['form_definition_id']);

        Gate::authorize('perform', [
            ResourceType::FormResponse, PermissionAction::Create, $channel, null, $data['geography_node_id'],
        ]);

        if (! $formDefinition->isPublished()) {
            return response()->json([
                'message' => "Form '{$formDefinition->code}' is not published and cannot accept submissions.",
            ], 422);
        }

        $response = FormResponse::create([
            ...$data,
            'submitted_by_user_id' => $request->user()->id,
            'channel' => $data['channel'] ?? $channel->value,
            'submitted_at' => now(),
        ]);

        return response()->json(['data' => $response->fresh(['formDefinition', 'beneficiary'])], 201);
    }

    /** Records an append-only history row before applying each edit. */
    public function update(UpdateFormResponseRequest $request, FormResponse $formResponse): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [
            ResourceType::FormResponse, PermissionAction::Edit, $channel, null, $formResponse->geography_node_id,
        ]);

        $formResponse->recordEdit($request->validated('values_json'), $request->user(), $request->validated('reason'));

        return response()->json(['data' => $formResponse->fresh(['history'])]);
    }
}
