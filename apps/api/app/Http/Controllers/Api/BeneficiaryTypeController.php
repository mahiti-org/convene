<?php

namespace App\Http\Controllers\Api;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Http\Concerns\ResolvesRequestChannel;
use App\Http\Controllers\Controller;
use App\Http\Requests\BeneficiaryType\StoreBeneficiaryTypeRequest;
use App\Http\Requests\BeneficiaryType\UpdateBeneficiaryTypeRequest;
use App\Http\Requests\DeactivateRequest;
use App\Models\BeneficiaryType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BeneficiaryTypeController extends Controller
{
    use ResolvesRequestChannel;

    public function index(Request $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::BeneficiaryType, PermissionAction::View, $channel]);

        $query = BeneficiaryType::query();

        if ($request->boolean('include_inactive')) {
            $query->withTrashed();
        }

        if ($request->filled('class')) {
            $query->where('class', $request->string('class')->value());
        }

        return response()->json(['data' => $query->orderBy('class')->orderBy('label')->get()]);
    }

    public function store(StoreBeneficiaryTypeRequest $request): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::BeneficiaryType, PermissionAction::Create, $channel]);

        $type = BeneficiaryType::create($request->validated());

        return response()->json(['data' => $type], 201);
    }

    public function update(UpdateBeneficiaryTypeRequest $request, BeneficiaryType $beneficiaryType): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::BeneficiaryType, PermissionAction::Edit, $channel]);

        $beneficiaryType->update($request->validated());

        return response()->json(['data' => $beneficiaryType]);
    }

    public function deactivate(DeactivateRequest $request, BeneficiaryType $beneficiaryType): JsonResponse
    {
        $channel = $this->resolveChannel($request);
        Gate::authorize('perform', [ResourceType::BeneficiaryType, PermissionAction::SoftDelete, $channel]);

        $beneficiaryType->deactivate($request->validated('reason'), $request->user());

        return response()->json(['data' => $beneficiaryType->fresh()]);
    }
}
