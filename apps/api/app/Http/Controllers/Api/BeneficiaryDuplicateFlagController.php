<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Models\BeneficiaryDuplicateFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Review queue for heuristic duplicate flags: view and dismiss only.
 */
class BeneficiaryDuplicateFlagController extends Controller
{
    use ResolvesRequestChannel;

    /**
     * Scoped via beneficiary (LocationScoped): unlike dedup checks, this user-facing read must
     * respect row-level scoping.
     */
    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Beneficiary, PermissionAction::View, $channel]);

        $status = $request->string('status', BeneficiaryDuplicateFlag::STATUS_OPEN)->value();

        $flags = BeneficiaryDuplicateFlag::query()
            ->whereHas('beneficiary')
            ->with(['beneficiary', 'matchedBeneficiary'])
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $flags]);
    }

    public function dismiss(Request $request, BeneficiaryDuplicateFlag $beneficiaryDuplicateFlag): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::Beneficiary, PermissionAction::Verify, $channel]);

        $beneficiaryDuplicateFlag->dismiss($request->user());

        return response()->json(['data' => $beneficiaryDuplicateFlag->fresh()]);
    }
}
