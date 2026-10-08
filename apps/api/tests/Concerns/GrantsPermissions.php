<?php

namespace Tests\Concerns;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Enums\ScopeType;
use App\Models\Grant;
use App\Models\LocationSet;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

trait GrantsPermissions
{
    /** Creates a Role with the given permission and an active org-wide (unrestricted) Grant for $user. */
    protected function grantPermission(
        User $user,
        ResourceType $resourceType,
        PermissionAction $action,
        Channel $channel = Channel::Any,
    ): Grant {
        return Grant::create([
            'user_id' => $user->id,
            'role_id' => $this->makeRoleWithPermission($resourceType, $action, $channel)->id,
            'scope_type' => ScopeType::Explicit->value,
        ]);
    }

    /** Same as grantPermission(), but the Grant's visibility is explicitly limited to $geographyNodeIds. */
    protected function grantPermissionScopedToLocations(
        User $user,
        ResourceType $resourceType,
        PermissionAction $action,
        array $geographyNodeIds,
        Channel $channel = Channel::Any,
    ): Grant {
        $locationSet = LocationSet::create(['owner_type' => 'grant', 'owner_id' => 0, 'name' => 'Test scope']);
        $locationSet->replaceMembers($geographyNodeIds);

        $grant = Grant::create([
            'user_id' => $user->id,
            'role_id' => $this->makeRoleWithPermission($resourceType, $action, $channel)->id,
            'location_set_id' => $locationSet->id,
            'scope_type' => ScopeType::Explicit->value,
        ]);

        $locationSet->update(['owner_id' => $grant->id]);

        return $grant;
    }

    private function makeRoleWithPermission(ResourceType $resourceType, PermissionAction $action, Channel $channel): Role
    {
        $role = Role::create(['name' => 'Test Role '.uniqid(), 'is_system' => false]);

        $permission = Permission::firstOrCreate([
            'resource_type' => $resourceType->value,
            'action' => $action->value,
            'channel' => $channel->value,
        ]);

        $role->permissions()->attach($permission);

        return $role;
    }
}
