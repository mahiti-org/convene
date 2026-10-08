<?php

namespace Tests\Feature\MasterDefinition;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\MasterDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class MasterDefinitionControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    public function test_user_without_create_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/master-definitions', [
            'category' => 'gender', 'code' => 'male', 'label' => 'Male',
        ]);

        $response->assertForbidden();
    }

    public function test_can_create_and_list_masters_by_category(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Master, PermissionAction::Create);
        $this->grantPermission($user, ResourceType::Master, PermissionAction::View);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/master-definitions', ['category' => 'gender', 'code' => 'male', 'label' => 'Male'])
            ->assertCreated();
        $this->postJson('/api/v1/master-definitions', ['category' => 'gender', 'code' => 'female', 'label' => 'Female'])
            ->assertCreated();
        $this->postJson('/api/v1/master-definitions', ['category' => 'caste', 'code' => 'general', 'label' => 'General'])
            ->assertCreated();

        $response = $this->getJson('/api/v1/master-definitions?category=gender');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertEqualsCanonicalizing(['male', 'female'], $codes);
    }

    public function test_duplicate_code_within_same_category_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Master, PermissionAction::Create);
        Sanctum::actingAs($user);

        MasterDefinition::create(['category' => 'gender', 'code' => 'male', 'label' => 'Male']);

        $response = $this->postJson('/api/v1/master-definitions', [
            'category' => 'gender', 'code' => 'male', 'label' => 'Male (duplicate)',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('code');
    }

    public function test_same_code_is_allowed_across_different_categories(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Master, PermissionAction::Create);
        Sanctum::actingAs($user);

        MasterDefinition::create(['category' => 'gender', 'code' => 'other', 'label' => 'Other']);

        $response = $this->postJson('/api/v1/master-definitions', [
            'category' => 'id_type', 'code' => 'other', 'label' => 'Other ID',
        ]);

        $response->assertCreated();
    }

    public function test_deactivate_requires_a_reason(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Master, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $master = MasterDefinition::create(['category' => 'gender', 'code' => 'male', 'label' => 'Male']);

        $response = $this->postJson("/api/v1/master-definitions/{$master->id}/deactivate", []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('reason');
        $this->assertDatabaseHas('master_definitions', ['id' => $master->id, 'deleted_at' => null]);
    }

    public function test_deactivate_with_reason_succeeds(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Master, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $master = MasterDefinition::create(['category' => 'gender', 'code' => 'other', 'label' => 'Other']);

        $this->postJson("/api/v1/master-definitions/{$master->id}/deactivate", ['reason' => 'Not used by any org'])
            ->assertOk();

        $this->assertSoftDeleted('master_definitions', ['id' => $master->id]);
    }
}
