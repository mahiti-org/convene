<?php

namespace App\Services\Auth;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Enums\ScopeType;
use App\Models\Grant;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Single authorization decision point: can() for features, allowedGeographyNodeIds() for rows.
 */
class PermissionEvaluator
{
    /**
     * Whether $user may do $action on $resourceType via $channel, optionally limited to a project
     * and geography node.
     */
    public function can(
        User $user,
        ResourceType $resourceType,
        PermissionAction $action,
        Channel $channel,
        ?int $projectId = null,
        ?int $geographyNodeId = null,
    ): bool {
        foreach ($this->coveringGrants($user, $projectId) as $grant) {
            if (! $this->roleGrantsPermission($grant, $resourceType, $action, $channel)) {
                continue;
            }

            if ($geographyNodeId === null) {
                return true;
            }

            if ($this->grantCoversNode($grant, $geographyNodeId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Node IDs covered by active Grants. Returns null (unrestricted) for an explicit Grant without
     * location_set; unresolved derived Grants add nothing.
     */
    public function allowedGeographyNodeIds(User $user, ?int $projectId = null): ?array
    {
        $ids = [];

        foreach ($this->coveringGrants($user, $projectId) as $grant) {
            if ($grant->locationSet === null) {
                if ($grant->scope_type === ScopeType::Explicit) {
                    return null; // deliberate org-wide grant: short-circuit, unrestricted
                }

                continue; // derived grant not yet resolved: contributes zero nodes, not everything
            }

            $ids = array_merge($ids, $grant->locationSet->geographyNodes()->pluck('geography_nodes.id')->all());
        }

        return array_values(array_unique($ids));
    }

    /** @return Collection<int, Grant> */
    private function coveringGrants(User $user, ?int $projectId): Collection
    {
        return $user->activeGrants()
            ->with(['role.permissions', 'locationSet.geographyNodes'])
            ->get()
            ->filter(function (Grant $grant) use ($projectId) {
                if ($projectId === null) {
                    return true;
                }

                return $grant->project_id === null || $grant->project_id === $projectId;
            });
    }

    private function roleGrantsPermission(Grant $grant, ResourceType $resourceType, PermissionAction $action, Channel $channel): bool
    {
        return $grant->role->permissions->contains(function ($permission) use ($resourceType, $action, $channel) {
            return $permission->resource_type === $resourceType
                && $permission->action === $action
                && $permission->channel->covers($channel);
        });
    }

    private function grantCoversNode(Grant $grant, int $geographyNodeId): bool
    {
        if ($grant->locationSet === null) {
            // Explicit without location_set = org-wide; derived without one is unresolved and
            // covers nothing.
            return $grant->scope_type === ScopeType::Explicit;
        }

        return $grant->locationSet->geographyNodes->contains('id', $geographyNodeId);
    }
}
