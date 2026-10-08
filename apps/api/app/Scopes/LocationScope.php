<?php

namespace App\Scopes;

use App\Services\Auth\ScopeContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Row-level location scoping. Bypass with withoutLocationScope() only for jobs, commands, or
 * integrity checks that never expose matched records.
 */
class LocationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $scopeContext = app(ScopeContext::class);

        // Never resolved for this request (e.g. no authenticated user reached
        // SetAuthorizationContext): deny by default rather than risk unrestricted rows.
        if (! $scopeContext->isResolved()) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $allowedNodeIds = $scopeContext->allowedGeographyNodeIds();

        // null means an unrestricted covering Grant: every node, so no WHERE clause.
        if ($allowedNodeIds === null) {
            return;
        }

        $builder->whereIn($model->qualifyColumn('geography_node_id'), $allowedNodeIds);
    }
}
