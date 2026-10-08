<?php

namespace App\Services\Auth;

use App\Enums\ScopeType;
use App\Models\GeographyNode;
use App\Models\Grant;
use App\Models\LocationSet;
use App\Models\ProjectLocation;
use App\Models\UserLocation;

/**
 * Derived-scope location_set_members: UserLocation intersect ProjectLocation nodes, plus descendants.
 */
class DerivedScopeResolver
{
    public function recomputeForGrant(Grant $grant): void
    {
        if ($grant->scope_type !== ScopeType::Derived || $grant->project_id === null) {
            return;
        }

        $userNodeIds = UserLocation::query()
            ->where('user_id', $grant->user_id)
            ->pluck('geography_node_id')
            ->all();

        $projectNodeIds = ProjectLocation::query()
            ->where('project_id', $grant->project_id)
            ->pluck('geography_node_id')
            ->all();

        $intersection = array_values(array_intersect($userNodeIds, $projectNodeIds));

        $expanded = [];
        foreach (GeographyNode::query()->whereIn('id', $intersection)->get() as $node) {
            $expanded = array_merge($expanded, $node->selfAndDescendantIds());
        }
        $expanded = array_values(array_unique($expanded));

        $locationSet = $grant->locationSet ?? LocationSet::create([
            'owner_type' => 'grant',
            'owner_id' => $grant->id,
            'name' => "Derived scope for grant {$grant->uuid}",
        ]);

        if ($grant->location_set_id !== $locationSet->id) {
            $grant->location_set_id = $locationSet->id;
            $grant->save();
        }

        $locationSet->replaceMembers($expanded);
    }

    /** Recompute every derived grant for a user whose org-level locations changed. */
    public function recomputeForUser(int $userId): void
    {
        Grant::query()
            ->where('user_id', $userId)
            ->where('scope_type', ScopeType::Derived->value)
            ->active()
            ->each(fn (Grant $grant) => $this->recomputeForGrant($grant));
    }

    /** Recompute every derived grant tied to a project whose location mapping changed. */
    public function recomputeForProject(int $projectId): void
    {
        Grant::query()
            ->where('project_id', $projectId)
            ->where('scope_type', ScopeType::Derived->value)
            ->active()
            ->each(fn (Grant $grant) => $this->recomputeForGrant($grant));
    }
}
