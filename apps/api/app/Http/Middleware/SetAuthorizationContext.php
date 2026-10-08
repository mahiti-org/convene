<?php

namespace App\Http\Middleware;

use App\Services\Auth\PermissionEvaluator;
use App\Services\Auth\ScopeContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active project (X-Project-Id header) and allowed geography nodes once per request
 * into ScopeContext.
 */
class SetAuthorizationContext
{
    public function __construct(
        private readonly PermissionEvaluator $evaluator,
        private readonly ScopeContext $scopeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $projectId = $request->header('X-Project-Id') !== null
                ? (int) $request->header('X-Project-Id')
                : null;

            $this->scopeContext->setActiveProjectId($projectId);
            $this->scopeContext->setAllowedGeographyNodeIds(
                $this->evaluator->allowedGeographyNodeIds($user, $projectId)
            );
        }

        return $next($request);
    }
}
