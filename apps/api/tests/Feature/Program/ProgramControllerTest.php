<?php

namespace Tests\Feature\Program;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class ProgramControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    public function test_user_without_create_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/programs', ['name' => 'Livelihoods'])->assertForbidden();
    }

    public function test_can_create_a_program_with_a_manager(): void
    {
        $user = User::factory()->create();
        $manager = User::factory()->create();
        $this->grantPermission($user, ResourceType::Program, PermissionAction::Create);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/programs', [
            'name' => 'Livelihoods', 'program_manager_user_id' => $manager->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('programs', ['name' => 'Livelihoods', 'program_manager_user_id' => $manager->id]);
    }

    public function test_deactivating_a_program_with_active_projects_is_blocked(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Program, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $program = Program::create(['name' => 'Livelihoods']);
        $project = Project::create(['program_id' => $program->id, 'name' => 'Watershed Development']);

        $response = $this->postJson("/api/v1/programs/{$program->id}/deactivate", ['reason' => 'test']);

        $response->assertStatus(422);
        $response->assertJsonPath('blocking_dependents.0.id', $project->id);
        $this->assertDatabaseHas('programs', ['id' => $program->id, 'deleted_at' => null]);
    }

    public function test_deactivating_a_program_with_no_active_projects_succeeds(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Program, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $program = Program::create(['name' => 'Livelihoods']);

        $this->postJson("/api/v1/programs/{$program->id}/deactivate", ['reason' => 'Not proceeding'])
            ->assertOk();

        $this->assertSoftDeleted('programs', ['id' => $program->id]);
    }
}
