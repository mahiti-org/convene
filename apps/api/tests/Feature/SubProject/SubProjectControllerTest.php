<?php

namespace Tests\Feature\SubProject;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class SubProjectControllerTest extends TestCase
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

        $this->postJson('/api/v1/sub-projects', [
            'project_id' => $this->project->id, 'name' => 'Phase 1',
        ])->assertForbidden();
    }

    public function test_can_create_and_list_sub_projects(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Project, PermissionAction::Create);
        $this->grantPermission($user, ResourceType::Project, PermissionAction::View);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sub-projects', ['project_id' => $this->project->id, 'name' => 'Phase 1'])
            ->assertCreated();

        $response = $this->getJson("/api/v1/sub-projects?project_id={$this->project->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
