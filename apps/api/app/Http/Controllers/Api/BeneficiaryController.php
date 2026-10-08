<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Beneficiary\StoreBeneficiaryRequest;
use App\Http\Requests\Beneficiary\UpdateBeneficiaryRequest;
use App\Http\Requests\DeactivateRequest;
use App\Models\Beneficiary;
use App\Models\BeneficiaryType;
use App\Services\Beneficiary\BeneficiaryDedupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BeneficiaryController extends Controller
{
    use ResolvesRequestChannel;

    public function __construct(
        private readonly BeneficiaryDedupService $dedupService,
    ) {}

    /**
     * Location-scoped list; dedup checks look wider, see BeneficiaryDedupService.
     */
    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Beneficiary, PermissionAction::View, $channel]);

        $query = Beneficiary::query()->with(['beneficiaryType', 'geographyNode']);

        if ($request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        if ($request->filled('beneficiary_type_id')) {
            $query->where('beneficiary_type_id', $request->integer('beneficiary_type_id'));
        }

        if ($request->filled('household_id')) {
            $query->where('household_id', $request->integer('household_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search')->value().'%');
        }

        return response()->json(['data' => $query->orderBy('name')->paginate($request->integer('per_page', 25))]);
    }

    /**
     * Search-before-create: visible near-matches plus an exact government-ID duplicate flag that never
     * exposes out-of-scope records.
     */
    public function search(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Beneficiary, PermissionAction::Search, $channel]);

        $request->validate([
            'name' => ['required_without:government_id_value', 'nullable', 'string'],
            'government_id_value' => ['nullable', 'string'],
            'government_id_type' => ['required_with:government_id_value', 'nullable', 'string'],
            'geography_node_id' => ['nullable', 'integer', 'exists:geography_nodes,id'],
        ]);

        $exactDuplicate = $this->dedupService->findExactDuplicate(
            $request->string('government_id_type')->value() ?: null,
            $request->string('government_id_value')->value() ?: null,
        );

        $visibleMatches = [];
        if ($request->filled('name') && $request->filled('geography_node_id')) {
            $visibleMatches = Beneficiary::query()
                ->with('beneficiaryType')
                ->where('geography_node_id', $request->integer('geography_node_id'))
                ->where('name', 'like', '%'.$request->string('name')->value().'%')
                ->get();
        }

        return response()->json([
            'exact_duplicate_exists' => $exactDuplicate !== null,
            'matches' => $visibleMatches,
        ]);
    }

    public function store(StoreBeneficiaryRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        $data = $request->validated();

        $beneficiaryType = BeneficiaryType::query()->findOrFail($data['beneficiary_type_id']);
        $resourceType = $beneficiaryType->isHousehold() ? ResourceType::Household : ResourceType::Beneficiary;

        Gate::authorize('perform', [
            $resourceType, PermissionAction::Create, $channel, null, $data['geography_node_id'],
        ]);

        if (! empty($data['household_id'])) {
            $household = Beneficiary::query()->with('beneficiaryType')->findOrFail($data['household_id']);
            if ($household->beneficiaryType->class !== BeneficiaryType::CLASS_INSTITUTIONAL) {
                return response()->json([
                    'message' => 'household_id must reference an institutional beneficiary.',
                ], 422);
            }
        }

        // Reject exact government-ID duplicates before the DB unique constraint, for a clean error.
        if (! empty($data['government_id_type']) && ! empty($data['government_id_value'])) {
            $duplicate = $this->dedupService->findExactDuplicate($data['government_id_type'], $data['government_id_value']);
            if ($duplicate !== null) {
                return response()->json([
                    'message' => 'A beneficiary with this government ID already exists.',
                ], 409);
            }
        }

        $beneficiary = Beneficiary::create([...$data, 'created_channel' => $data['created_channel'] ?? $channel->value]);

        $this->dedupService->flagPotentialDuplicates($beneficiary);

        return response()->json([
            'data' => $beneficiary->fresh(['beneficiaryType', 'geographyNode']),
            'potential_duplicate_flags' => $beneficiary->duplicateFlagsRaised()->count(),
        ], 201);
    }

    public function update(UpdateBeneficiaryRequest $request, Beneficiary $beneficiary): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        $resourceType = $beneficiary->beneficiaryType->isHousehold() ? ResourceType::Household : ResourceType::Beneficiary;

        Gate::authorize('perform', [
            $resourceType, PermissionAction::Edit, $channel, null, $beneficiary->geography_node_id,
        ]);

        $beneficiary->update($request->validated());

        return response()->json(['data' => $beneficiary->fresh(['beneficiaryType', 'geographyNode'])]);
    }

    public function deactivate(DeactivateRequest $request, Beneficiary $beneficiary): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        $resourceType = $beneficiary->beneficiaryType->isHousehold() ? ResourceType::Household : ResourceType::Beneficiary;

        Gate::authorize('perform', [
            $resourceType, PermissionAction::SoftDelete, $channel, null, $beneficiary->geography_node_id,
        ]);

        $beneficiary->deactivate($request->validated('reason'), $request->user());

        return response()->json(['data' => $beneficiary->fresh()]);
    }
}
