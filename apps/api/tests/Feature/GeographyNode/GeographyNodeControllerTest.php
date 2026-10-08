<?php

namespace Tests\Feature\GeographyNode;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\GeographyNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class GeographyNodeControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    public function test_user_without_create_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/geography-nodes', ['level' => 0, 'label' => 'State X']);

        $response->assertForbidden();
    }

    public function test_user_can_build_a_state_to_village_hierarchy(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::Create);
        Sanctum::actingAs($user);

        $state = $this->postJson('/api/v1/geography-nodes', ['level' => 0, 'label' => 'State X', 'code' => 'ST-X'])
            ->assertCreated()
            ->json('data');

        $district = $this->postJson('/api/v1/geography-nodes', [
            'parent_id' => $state['id'], 'level' => 1, 'label' => 'District X1', 'code' => 'DT-X1',
        ])->assertCreated()->json('data');

        $village = $this->postJson('/api/v1/geography-nodes', [
            'parent_id' => $district['id'], 'level' => 4, 'label' => 'Village X1A', 'code' => 'VL-X1A',
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('geography_nodes', ['id' => $village['id'], 'parent_id' => $district['id']]);

        // Tree endpoint reflects the full nesting.
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::View);
        $tree = $this->getJson('/api/v1/geography-nodes/tree')->assertOk()->json('data');

        $this->assertCount(1, $tree);
        $this->assertSame('State X', $tree[0]['label']);
        $this->assertSame('District X1', $tree[0]['children'][0]['label']);
        $this->assertSame('Village X1A', $tree[0]['children'][0]['children'][0]['label']);
    }

    public function test_deactivating_a_leaf_node_succeeds(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $leaf = GeographyNode::create(['level' => 0, 'label' => 'Standalone Node']);

        $response = $this->postJson("/api/v1/geography-nodes/{$leaf->id}/deactivate", [
            'reason' => 'Duplicate entry, merged into another node',
        ]);

        $response->assertOk();
        $this->assertSoftDeleted('geography_nodes', ['id' => $leaf->id]);
        $this->assertSame(
            'Duplicate entry, merged into another node',
            $leaf->fresh()->deactivation_reason,
        );
    }

    public function test_deactivating_a_node_with_active_children_is_blocked(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $parent = GeographyNode::create(['level' => 0, 'label' => 'Parent District']);
        $child = GeographyNode::create(['level' => 1, 'label' => 'Child Village', 'parent_id' => $parent->id]);

        $response = $this->postJson("/api/v1/geography-nodes/{$parent->id}/deactivate", [
            'reason' => 'Attempting to remove',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('blocking_dependents.0.id', $child->id);
        $this->assertDatabaseHas('geography_nodes', ['id' => $parent->id, 'deleted_at' => null]);
    }

    public function test_deactivating_a_node_after_its_child_is_deactivated_succeeds(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $parent = GeographyNode::create(['level' => 0, 'label' => 'Parent District']);
        $child = GeographyNode::create(['level' => 1, 'label' => 'Child Village', 'parent_id' => $parent->id]);

        $this->postJson("/api/v1/geography-nodes/{$child->id}/deactivate", ['reason' => 'No longer active'])
            ->assertOk();

        $this->postJson("/api/v1/geography-nodes/{$parent->id}/deactivate", ['reason' => 'All children deactivated'])
            ->assertOk();

        $this->assertSoftDeleted('geography_nodes', ['id' => $parent->id]);
    }

    public function test_soft_delete_is_denied_on_mobile_channel(): void
    {
        $user = User::factory()->create();
        // Permission seeded only for Channel::Web; mobile requests must be denied.
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $leaf = GeographyNode::create(['level' => 0, 'label' => 'Standalone Node']);

        $response = $this->withHeader('X-Channel', 'mobile')
            ->postJson("/api/v1/geography-nodes/{$leaf->id}/deactivate", ['reason' => 'Test']);

        $response->assertForbidden();
        $this->assertDatabaseHas('geography_nodes', ['id' => $leaf->id, 'deleted_at' => null]);
    }

    public function test_search_filters_by_label(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::View);
        Sanctum::actingAs($user);

        GeographyNode::create(['level' => 0, 'label' => 'Alpha District']);
        GeographyNode::create(['level' => 0, 'label' => 'Beta District']);

        $response = $this->getJson('/api/v1/geography-nodes?search=Alpha');

        $response->assertOk();
        $labels = collect($response->json('data'))->pluck('label')->all();
        $this->assertSame(['Alpha District'], $labels);
    }

    public function test_deactivated_nodes_are_excluded_unless_include_inactive_is_set(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::View);
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $node = GeographyNode::create(['level' => 0, 'label' => 'To Deactivate']);
        $node->deactivate('test');

        $this->getJson('/api/v1/geography-nodes')
            ->assertOk()
            ->assertJsonMissing(['label' => 'To Deactivate']);

        $this->getJson('/api/v1/geography-nodes?include_inactive=1')
            ->assertOk()
            ->assertJsonFragment(['label' => 'To Deactivate']);
    }
}
