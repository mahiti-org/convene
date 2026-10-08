<?php

namespace Tests\Feature\Auth;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Enums\ScopeType;
use App\Models\GeographyNode;
use App\Models\Grant;
use App\Models\LocationSet;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserLocation;
use App\Services\Auth\DerivedScopeResolver;
use App\Services\Auth\PermissionEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PermissionEvaluatorTest extends TestCase
{
    use RefreshDatabase;

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

    private function makeGeographyTree(): array
    {
        // District X -> Village X1
        $districtX = GeographyNode::create(['level' => 0, 'label' => 'District X']);
        $villageX1 = GeographyNode::create(['level' => 1, 'label' => 'Village X1', 'parent_id' => $districtX->id]);

        // District Y -> Village Y1
        $districtY = GeographyNode::create(['level' => 0, 'label' => 'District Y']);
        $villageY1 = GeographyNode::create(['level' => 1, 'label' => 'Village Y1', 'parent_id' => $districtY->id]);

        return compact('districtX', 'villageX1', 'districtY', 'villageY1');
    }

    public function test_grant_scoped_to_one_location_does_not_cover_another_location(): void
    {
        ['districtX' => $districtX, 'villageX1' => $villageX1, 'districtY' => $districtY] = $this->makeGeographyTree();

        $user = User::factory()->create();
        $role = $this->makeRoleWithPermission(ResourceType::Beneficiary, PermissionAction::View, Channel::Web);

        $locationSet = LocationSet::create(['owner_type' => 'grant', 'owner_id' => 0, 'name' => 'District X scope']);
        // Grant explicitly scoped to District X; cascades to its children (Village X1).
        $locationSet->replaceMembers($districtX->selfAndDescendantIds());

        $grant = Grant::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'location_set_id' => $locationSet->id,
            'scope_type' => ScopeType::Explicit->value,
        ]);
        $locationSet->update(['owner_id' => $grant->id]);

        $evaluator = app(PermissionEvaluator::class);

        // Covers itself and its own child (cascade rule).
        $this->assertTrue($evaluator->can($user, ResourceType::Beneficiary, PermissionAction::View, Channel::Web, null, $districtX->id));
        $this->assertTrue($evaluator->can($user, ResourceType::Beneficiary, PermissionAction::View, Channel::Web, null, $villageX1->id));

        // Does NOT cover an unrelated location tree (cross-location denial).
        $this->assertFalse($evaluator->can($user, ResourceType::Beneficiary, PermissionAction::View, Channel::Web, null, $districtY->id));
    }

    public function test_derived_scope_grant_computes_intersection_of_user_and_project_locations(): void
    {
        Bus::fake(); // don't actually queue-process the recompute job from model observers

        ['districtX' => $districtX, 'villageX1' => $villageX1, 'districtY' => $districtY] = $this->makeGeographyTree();

        $user = User::factory()->create();
        $role = $this->makeRoleWithPermission(ResourceType::Beneficiary, PermissionAction::View, Channel::Any);

        $program = Program::create(['name' => 'Test Program']);
        $projectId = Project::create(['program_id' => $program->id, 'name' => 'Test Project'])->id;

        // User is assigned District X and District Y at the org level...
        UserLocation::create(['user_id' => $user->id, 'geography_node_id' => $districtX->id]);
        UserLocation::create(['user_id' => $user->id, 'geography_node_id' => $districtY->id]);

        // ...but Project 42 only covers District X.
        ProjectLocation::create(['project_id' => $projectId, 'geography_node_id' => $districtX->id]);

        $grant = Grant::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'project_id' => $projectId,
            'scope_type' => ScopeType::Derived->value,
        ]);

        // Bus::fake() stops the observer-dispatched job, so run the resolver synchronously here.
        app(DerivedScopeResolver::class)->recomputeForGrant($grant->fresh());

        $evaluator = app(PermissionEvaluator::class);
        $allowed = $evaluator->allowedGeographyNodeIds($user, $projectId);

        // Intersection = District X (plus descendant Village X1, cascade rule), not District Y.
        $this->assertContains($districtX->id, $allowed);
        $this->assertContains($villageX1->id, $allowed);
        $this->assertNotContains($districtY->id, $allowed);
    }

    public function test_revoked_grant_grants_no_access(): void
    {
        $user = User::factory()->create();
        $role = $this->makeRoleWithPermission(ResourceType::Report, PermissionAction::View, Channel::Any);

        $grant = Grant::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::Explicit->value,
        ]);
        $grant->revoke();

        $evaluator = app(PermissionEvaluator::class);

        $this->assertFalse($evaluator->can($user, ResourceType::Report, PermissionAction::View, Channel::Web));
    }

    public function test_channel_any_permission_covers_both_web_and_mobile(): void
    {
        $user = User::factory()->create();
        $role = $this->makeRoleWithPermission(ResourceType::Dashboard, PermissionAction::View, Channel::Any);

        Grant::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::Explicit->value,
        ]);

        $evaluator = app(PermissionEvaluator::class);

        $this->assertTrue($evaluator->can($user, ResourceType::Dashboard, PermissionAction::View, Channel::Web));
        $this->assertTrue($evaluator->can($user, ResourceType::Dashboard, PermissionAction::View, Channel::Mobile));
    }

    public function test_derived_grant_with_no_location_set_yet_covers_nothing_not_everything(): void
    {
        // Regression: an unresolved Derived Grant must cover nothing, not fall back to unrestricted
        // like an Explicit one.
        $user = User::factory()->create();
        $role = $this->makeRoleWithPermission(ResourceType::Beneficiary, PermissionAction::View, Channel::Any);

        $program = Program::create(['name' => 'Test Program']);
        $project = Project::create(['program_id' => $program->id, 'name' => 'Test Project']);

        // No UserLocation/ProjectLocation at all, so the derived intersection is empty.
        $grant = Grant::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'scope_type' => ScopeType::Derived->value,
        ]);

        $evaluator = app(PermissionEvaluator::class);

        $this->assertSame([], $evaluator->allowedGeographyNodeIds($user, $project->id));

        $districtX = GeographyNode::create(['level' => 0, 'label' => 'District X']);
        $this->assertFalse($evaluator->can(
            $user, ResourceType::Beneficiary, PermissionAction::View, Channel::Any, $project->id, $districtX->id,
        ));
    }

    public function test_creating_a_derived_grant_eagerly_resolves_its_scope(): void
    {
        // Regression: Grant::booted() must resolve a Derived Grant's location_set on creation, not
        // on a later event.
        $user = User::factory()->create();
        $role = $this->makeRoleWithPermission(ResourceType::Beneficiary, PermissionAction::View, Channel::Any);

        $program = Program::create(['name' => 'Test Program']);
        $project = Project::create(['program_id' => $program->id, 'name' => 'Test Project']);
        $district = GeographyNode::create(['level' => 0, 'label' => 'District X']);

        // Locations set up BEFORE the Grant is created.
        UserLocation::create(['user_id' => $user->id, 'geography_node_id' => $district->id]);
        ProjectLocation::create(['project_id' => $project->id, 'geography_node_id' => $district->id]);

        $grant = Grant::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'scope_type' => ScopeType::Derived->value,
        ]);

        // No manual recomputeForGrant() call here; Grant::booted() must have already done it.
        $this->assertNotNull($grant->fresh()->location_set_id);
        $this->assertContains($district->id, app(PermissionEvaluator::class)->allowedGeographyNodeIds($user, $project->id));
    }

    public function test_org_wide_grant_with_no_location_set_returns_null_meaning_unrestricted(): void
    {
        // Regression: allowedGeographyNodeIds() must return null (unrestricted), not [], for a
        // Grant without location_set.
        $user = User::factory()->create();
        $role = $this->makeRoleWithPermission(ResourceType::Beneficiary, PermissionAction::View, Channel::Any);

        Grant::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => ScopeType::Explicit->value,
        ]);

        $evaluator = app(PermissionEvaluator::class);

        $this->assertNull($evaluator->allowedGeographyNodeIds($user));
    }
}
