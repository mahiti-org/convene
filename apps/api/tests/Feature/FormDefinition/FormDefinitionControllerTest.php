<?php

namespace Tests\Feature\FormDefinition;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\FormDefinition;
use App\Models\FormDefinitionAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class FormDefinitionControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    private array $validSchema = [
        ['key' => 'name', 'type' => 'single_line_text', 'label' => 'Name', 'required' => true],
        ['key' => 'village', 'type' => 'cascading_select', 'label' => 'Village', 'options' => ['start_geography_level' => 0]],
        ['key' => 'members', 'type' => 'repeat_group', 'label' => 'Household Members', 'options' => ['child_widget_keys' => ['name']]],
        ['key' => 'aadhaar', 'type' => 'government_id', 'label' => 'Aadhaar', 'options' => ['id_type' => 'aadhaar']],
    ];

    public function test_user_without_create_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/form-definitions', [
            'code' => 'household_registration', 'name' => 'Household Registration', 'periodicity' => 'one_time',
        ])->assertForbidden();
    }

    public function test_can_create_a_draft_form_definition(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormDefinition, PermissionAction::Create);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/form-definitions', [
            'code' => 'household_registration', 'name' => 'Household Registration',
            'periodicity' => 'one_time', 'schema_json' => $this->validSchema,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', FormDefinition::STATUS_DRAFT);
        $response->assertJsonPath('data.version', 1);
    }

    public function test_publishing_an_empty_draft_fails(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormDefinition, PermissionAction::Create);
        $this->grantPermission($user, ResourceType::FormDefinition, PermissionAction::Approve);
        Sanctum::actingAs($user);

        $form = FormDefinition::create(['code' => 'test_form', 'name' => 'Test Form', 'periodicity' => 'one_time']);

        $response = $this->postJson("/api/v1/form-definitions/{$form->id}/publish");

        $response->assertStatus(422);
        $this->assertSame(FormDefinition::STATUS_DRAFT, $form->fresh()->status);
    }

    public function test_publishing_a_schema_with_invalid_widgets_fails(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormDefinition, PermissionAction::Approve);
        Sanctum::actingAs($user);

        $form = FormDefinition::create([
            'code' => 'test_form', 'name' => 'Test Form', 'periodicity' => 'one_time',
            'schema_json' => [['key' => 'x', 'type' => 'not_a_widget', 'label' => 'X']],
        ]);

        $this->postJson("/api/v1/form-definitions/{$form->id}/publish")->assertStatus(422);
    }

    public function test_full_draft_publish_edit_creates_new_version_and_old_responses_still_render_against_v1(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormDefinition, PermissionAction::Create);
        $this->grantPermission($user, ResourceType::FormDefinition, PermissionAction::Approve);
        $this->grantPermission($user, ResourceType::FormDefinition, PermissionAction::Edit);
        Sanctum::actingAs($user);

        // Create a draft with all four data-model-critical widgets plus a plain one.
        $v1 = $this->postJson('/api/v1/form-definitions', [
            'code' => 'household_registration', 'name' => 'Household Registration',
            'periodicity' => 'one_time', 'schema_json' => $this->validSchema,
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/form-definitions/{$v1['id']}/publish")->assertOk();
        $this->assertSame(FormDefinition::STATUS_PUBLISHED, FormDefinition::find($v1['id'])->status);

        // Editing the published form must create v2, not mutate v1.
        $updateResponse = $this->patchJson("/api/v1/form-definitions/{$v1['id']}", [
            'name' => 'Household Registration (Updated)',
        ]);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('created_new_version', true);
        $v2Id = $updateResponse->json('data.id');
        $this->assertNotSame($v1['id'], $v2Id);

        $v1Fresh = FormDefinition::find($v1['id']);
        $v2Fresh = FormDefinition::find($v2Id);

        $this->assertSame('Household Registration', $v1Fresh->name); // v1 untouched
        $this->assertSame('Household Registration (Updated)', $v2Fresh->name);
        $this->assertSame(FormDefinition::STATUS_PUBLISHED, $v1Fresh->status); // still published
        $this->assertSame(FormDefinition::STATUS_DRAFT, $v2Fresh->status); // new version starts as draft
        $this->assertSame(2, $v2Fresh->version);
        $this->assertSame($v1Fresh->id, $v2Fresh->predecessor_id);
        $this->assertSame('household_registration', $v2Fresh->code);
    }

    public function test_assign_and_unassign_a_form_to_a_role(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormDefinition, PermissionAction::Assign);
        Sanctum::actingAs($user);

        $role = Role::create(['name' => 'Field Staff']);

        $this->postJson('/api/v1/form-definitions/household_registration/assign', [
            'assignable_type' => 'role', 'assignable_id' => $role->id,
        ])->assertCreated();

        $this->assertDatabaseHas('form_definition_assignments', [
            'form_code' => 'household_registration', 'assignable_type' => 'role', 'assignable_id' => $role->id,
        ]);

        $this->postJson('/api/v1/form-definitions/household_registration/unassign', [
            'assignable_type' => 'role', 'assignable_id' => $role->id,
        ])->assertOk();

        $this->assertSame(0, FormDefinitionAssignment::where('form_code', 'household_registration')->count());
    }

    public function test_deactivate_requires_web_channel(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormDefinition, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $form = FormDefinition::create(['code' => 'test_form', 'name' => 'Test Form', 'periodicity' => 'one_time']);

        $this->withHeader('X-Channel', 'mobile')
            ->postJson("/api/v1/form-definitions/{$form->id}/deactivate", ['reason' => 'test'])
            ->assertForbidden();

        $this->withHeader('X-Channel', 'web')
            ->postJson("/api/v1/form-definitions/{$form->id}/deactivate", ['reason' => 'Retiring form'])
            ->assertOk();

        $this->assertSoftDeleted('form_definitions', ['id' => $form->id]);
    }
}
