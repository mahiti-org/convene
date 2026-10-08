<?php

namespace Tests\Feature\Project;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Enums\ScopeType;
use App\Models\Activity;
use App\Models\GeographyNode;
use App\Models\Grant;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectLocation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserLocation;
use App\Services\Auth\PermissionEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    private Program $program;

    protected function setUp(): void
    {
        parent::setUp();
        $this->program = Program::create(['name' => 'Livelihoods']);
    }

    public function test_user_without_create_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/projects', [
            'program_id' => $this->program->id, 'name' => 'Watershed Development',
        ])->assertForbidden();
    }

    public function test_can_create_a_project_under_a_program(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Project, PermissionAction::Create);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/projects', [
            'program_id' => $this->program->id, 'name' => 'Watershed Development',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('projects', ['name' => 'Watershed Development', 'program_id' => $this->program->id]);
    }

    public function test_filter_projects_by_program(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Project, PermissionAction::View);
        Sanctum::actingAs($user);

        $otherProgram = Program::create(['name' => 'Health']);
        Project::create(['program_id' => $this->program->id, 'name' => 'Watershed Development']);
        Project::create(['program_id' => $otherProgram->id, 'name' => 'Maternal Health']);

        $response = $this->getJson("/api/v1/projects?program_id={$this->program->id}");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertSame(['Watershed Development'], $names);
    }

    public function test_deactivating_a_project_with_active_activities_is_blocked(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Project, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $project = Project::create(['program_id' => $this->program->id, 'name' => 'Watershed Development']);
        Activity::create(['project_id' => $project->id, 'name' => 'Check-dam construction']);

        $response = $this->postJson("/api/v1/projects/{$project->id}/deactivate", ['reason' => 'test']);

        $response->assertStatus(422);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    public function test_attaching_a_location_to_a_project_recomputes_derived_grants(): void
    {
        $fieldStaff = User::factory()->create();
        $admin = User::factory()->create();
        $this->grantPermission($admin, ResourceType::Project, PermissionAction::Assign);
        Sanctum::actingAs($admin);

        $project = Project::create(['program_id' => $this->program->id, 'name' => 'Watershed Development']);
        $location = GeographyNode::create(['level' => 0, 'label' => 'District X']);

        // User has the location org-wide and a derived Grant; with no project_locations yet,
        // derived scope is empty.
        UserLocation::create(['user_id' => $fieldStaff->id, 'geography_node_id' => $location->id]);
        $role = Role::create(['name' => 'Field Staff Test']);
        $permission = Permission::firstOrCreate([
            'resource_type' => ResourceType::Beneficiary->value, 'action' => PermissionAction::View->value, 'channel' => Channel::Any->value,
        ]);
        $role->permissions()->attach($permission);
        $grant = Grant::create([
            'user_id' => $fieldStaff->id, 'role_id' => $role->id, 'project_id' => $project->id,
            'scope_type' => ScopeType::Derived->value,
        ]);

        $this->assertSame([], app(PermissionEvaluator::class)->allowedGeographyNodeIds($fieldStaff, $project->id));

        // Attaching the location the project now covers should recompute the derived Grant
        // (via the ProjectLocation model event dispatching RecomputeDerivedLocationSets).
        $this->postJson("/api/v1/projects/{$project->id}/locations", ['geography_node_id' => $location->id])
            ->assertCreated();

        $this->assertContains($location->id, app(PermissionEvaluator::class)->allowedGeographyNodeIds($fieldStaff, $project->id));
    }

    public function test_detaching_a_location_recomputes_derived_grants(): void
    {
        $fieldStaff = User::factory()->create();
        $admin = User::factory()->create();
        $this->grantPermission($admin, ResourceType::Project, PermissionAction::Assign);
        Sanctum::actingAs($admin);

        $project = Project::create(['program_id' => $this->program->id, 'name' => 'Watershed Development']);
        $location = GeographyNode::create(['level' => 0, 'label' => 'District X']);

        UserLocation::create(['user_id' => $fieldStaff->id, 'geography_node_id' => $location->id]);
        ProjectLocation::create(['project_id' => $project->id, 'geography_node_id' => $location->id]);

        $role = Role::create(['name' => 'Field Staff Test']);
        $permission = Permission::firstOrCreate([
            'resource_type' => ResourceType::Beneficiary->value, 'action' => PermissionAction::View->value, 'channel' => Channel::Any->value,
        ]);
        $role->permissions()->attach($permission);
        Grant::create([
            'user_id' => $fieldStaff->id, 'role_id' => $role->id, 'project_id' => $project->id,
            'scope_type' => ScopeType::Derived->value,
        ]);

        $this->assertContains($location->id, app(PermissionEvaluator::class)->allowedGeographyNodeIds($fieldStaff, $project->id));

        $this->deleteJson("/api/v1/projects/{$project->id}/locations/{$location->id}")->assertOk();

        $this->assertNotContains($location->id, app(PermissionEvaluator::class)->allowedGeographyNodeIds($fieldStaff, $project->id));
    }
}
