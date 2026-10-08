<?php

namespace Tests\Feature\Activity;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\Activity;
use App\Models\BeneficiaryType;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class ActivityControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $program = Program::create(['name' => 'Livelihoods']);
        $this->project = Project::create(['program_id' => $program->id, 'name' => 'Watershed Development']);
    }

    public function test_user_without_create_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/activities', [
            'project_id' => $this->project->id, 'name' => 'Check-dam construction',
        ])->assertForbidden();
    }

    public function test_can_create_an_activity_mapped_to_a_beneficiary_type(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Activity, PermissionAction::Create);
        Sanctum::actingAs($user);

        $type = BeneficiaryType::create(['class' => BeneficiaryType::CLASS_INDIVIDUAL, 'code' => 'farmers', 'label' => 'Farmers']);

        $response = $this->postJson('/api/v1/activities', [
            'project_id' => $this->project->id,
            'name' => 'Check-dam construction',
            'beneficiary_type_id' => $type->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('activities', ['name' => 'Check-dam construction', 'beneficiary_type_id' => $type->id]);
    }

    public function test_can_create_a_sub_activity(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Activity, PermissionAction::Create);
        $this->grantPermission($user, ResourceType::Activity, PermissionAction::View);
        Sanctum::actingAs($user);

        $parent = Activity::create(['project_id' => $this->project->id, 'name' => 'Watershed activities']);

        $this->postJson('/api/v1/activities', [
            'project_id' => $this->project->id, 'parent_activity_id' => $parent->id, 'name' => 'Check-dam construction',
        ])->assertCreated();

        $response = $this->getJson("/api/v1/activities?parent_activity_id={$parent->id}");
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_deactivating_an_activity_with_active_sub_activities_is_blocked(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Activity, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $parent = Activity::create(['project_id' => $this->project->id, 'name' => 'Watershed activities']);
        $child = Activity::create(['project_id' => $this->project->id, 'parent_activity_id' => $parent->id, 'name' => 'Check-dam construction']);

        $response = $this->postJson("/api/v1/activities/{$parent->id}/deactivate", ['reason' => 'test']);

        $response->assertStatus(422);
        $response->assertJsonPath('blocking_dependents.0.id', $child->id);
    }
}
