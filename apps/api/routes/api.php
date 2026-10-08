<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\BeneficiaryController;
use App\Http\Controllers\Api\BeneficiaryDuplicateFlagController;
use App\Http\Controllers\Api\BeneficiaryTypeController;
use App\Http\Controllers\Api\FormDefinitionController;
use App\Http\Controllers\Api\FormResponseController;
use App\Http\Controllers\Api\GeographyNodeController;
use App\Http\Controllers\Api\MasterDefinitionController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SubProjectController;
use App\Http\Middleware\SetAuthorizationContext;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

// Order matters: auth:sanctum, then SetAuthorizationContext, then SubstituteBindings, else
// LocationScope fails closed and bindings 404.
Route::middleware(['auth:sanctum', SetAuthorizationContext::class, SubstituteBindings::class])->prefix('v1')->group(function () {
    Route::get('/me/effective-permissions', [MeController::class, 'effectivePermissions']);

    Route::get('/geography-nodes', [GeographyNodeController::class, 'index']);
    Route::get('/geography-nodes/tree', [GeographyNodeController::class, 'tree']);
    Route::post('/geography-nodes', [GeographyNodeController::class, 'store']);
    Route::patch('/geography-nodes/{geographyNode}', [GeographyNodeController::class, 'update']);
    Route::post('/geography-nodes/{geographyNode}/deactivate', [GeographyNodeController::class, 'deactivate']);
    Route::post('/geography-nodes/import', [GeographyNodeController::class, 'import']);

    Route::get('/master-definitions', [MasterDefinitionController::class, 'index']);
    Route::post('/master-definitions', [MasterDefinitionController::class, 'store']);
    Route::patch('/master-definitions/{masterDefinition}', [MasterDefinitionController::class, 'update']);
    Route::post('/master-definitions/{masterDefinition}/deactivate', [MasterDefinitionController::class, 'deactivate']);
    Route::post('/master-definitions/import', [MasterDefinitionController::class, 'import']);

    Route::get('/beneficiary-types', [BeneficiaryTypeController::class, 'index']);
    Route::post('/beneficiary-types', [BeneficiaryTypeController::class, 'store']);
    Route::patch('/beneficiary-types/{beneficiaryType}', [BeneficiaryTypeController::class, 'update']);
    Route::post('/beneficiary-types/{beneficiaryType}/deactivate', [BeneficiaryTypeController::class, 'deactivate']);

    Route::get('/beneficiaries', [BeneficiaryController::class, 'index']);
    Route::get('/beneficiaries/search', [BeneficiaryController::class, 'search']);
    Route::post('/beneficiaries', [BeneficiaryController::class, 'store']);
    Route::patch('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'update']);
    Route::post('/beneficiaries/{beneficiary}/deactivate', [BeneficiaryController::class, 'deactivate']);

    Route::get('/beneficiary-duplicate-flags', [BeneficiaryDuplicateFlagController::class, 'index']);
    Route::post('/beneficiary-duplicate-flags/{beneficiaryDuplicateFlag}/dismiss', [BeneficiaryDuplicateFlagController::class, 'dismiss']);

    Route::get('/programs', [ProgramController::class, 'index']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::patch('/programs/{program}', [ProgramController::class, 'update']);
    Route::post('/programs/{program}/deactivate', [ProgramController::class, 'deactivate']);

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::patch('/projects/{project}', [ProjectController::class, 'update']);
    Route::post('/projects/{project}/deactivate', [ProjectController::class, 'deactivate']);
    Route::post('/projects/{project}/locations', [ProjectController::class, 'attachLocation']);
    Route::delete('/projects/{project}/locations/{geographyNode}', [ProjectController::class, 'detachLocation']);

    Route::get('/sub-projects', [SubProjectController::class, 'index']);
    Route::post('/sub-projects', [SubProjectController::class, 'store']);
    Route::patch('/sub-projects/{subProject}', [SubProjectController::class, 'update']);
    Route::post('/sub-projects/{subProject}/deactivate', [SubProjectController::class, 'deactivate']);

    Route::get('/activities', [ActivityController::class, 'index']);
    Route::post('/activities', [ActivityController::class, 'store']);
    Route::patch('/activities/{activity}', [ActivityController::class, 'update']);
    Route::post('/activities/{activity}/deactivate', [ActivityController::class, 'deactivate']);

    Route::get('/form-definitions', [FormDefinitionController::class, 'index']);
    Route::post('/form-definitions', [FormDefinitionController::class, 'store']);
    Route::patch('/form-definitions/{formDefinition}', [FormDefinitionController::class, 'update']);
    Route::post('/form-definitions/{formDefinition}/publish', [FormDefinitionController::class, 'publish']);
    Route::post('/form-definitions/{formDefinition}/deactivate', [FormDefinitionController::class, 'deactivate']);
    Route::post('/form-definitions/{formCode}/assign', [FormDefinitionController::class, 'assign']);
    Route::post('/form-definitions/{formCode}/unassign', [FormDefinitionController::class, 'unassign']);

    Route::get('/form-responses', [FormResponseController::class, 'index']);
    Route::post('/form-responses', [FormResponseController::class, 'store']);
    Route::patch('/form-responses/{formResponse}', [FormResponseController::class, 'update']);
});
