<?php

namespace Tests\Feature\FormResponse;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\FormDefinition;
use App\Models\FormResponse;
use App\Models\GeographyNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class FormResponseControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    private GeographyNode $location;

    private FormDefinition $publishedForm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = GeographyNode::create(['level' => 0, 'label' => 'District X']);

        $this->publishedForm = FormDefinition::create([
            'code' => 'household_registration', 'name' => 'Household Registration', 'periodicity' => 'one_time',
            'schema_json' => [['key' => 'name', 'type' => 'single_line_text', 'label' => 'Name']],
        ]);
        $this->publishedForm->publish();
    }

    public function test_user_without_create_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/form-responses', [
            'form_definition_id' => $this->publishedForm->id,
            'geography_node_id' => $this->location->id,
            'values_json' => ['name' => 'Asha Devi'],
        ])->assertForbidden();
    }

    public function test_can_submit_a_response_against_a_published_form(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormResponse, PermissionAction::Create);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/form-responses', [
            'form_definition_id' => $this->publishedForm->id,
            'geography_node_id' => $this->location->id,
            'values_json' => ['name' => 'Asha Devi'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('form_responses', ['form_definition_id' => $this->publishedForm->id]);
    }

    public function test_cannot_submit_against_a_draft_form(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormResponse, PermissionAction::Create);
        Sanctum::actingAs($user);

        $draft = FormDefinition::create(['code' => 'draft_form', 'name' => 'Draft Form', 'periodicity' => 'one_time']);

        $response = $this->postJson('/api/v1/form-responses', [
            'form_definition_id' => $draft->id,
            'geography_node_id' => $this->location->id,
            'values_json' => ['name' => 'Asha Devi'],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, FormResponse::count());
    }

    public function test_editing_a_response_records_history_and_applies_the_change(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormResponse, PermissionAction::Create);
        $this->grantPermission($user, ResourceType::FormResponse, PermissionAction::Edit);
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/form-responses', [
            'form_definition_id' => $this->publishedForm->id,
            'geography_node_id' => $this->location->id,
            'values_json' => ['name' => 'Asha Devi'],
        ])->assertCreated()->json('data');

        $this->patchJson("/api/v1/form-responses/{$created['id']}", [
            'values_json' => ['name' => 'Asha Devi (corrected)'],
            'reason' => 'Spelling correction',
        ])->assertOk();

        $updated = FormResponse::find($created['id']);
        $this->assertSame('Asha Devi (corrected)', $updated->values_json['name']);
        $this->assertSame(1, $updated->history()->count());
        $this->assertSame('Asha Devi', $updated->history()->first()->diff_json['before']['name']);
    }

    public function test_response_keeps_referencing_its_original_form_version_after_a_new_version_is_created(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::FormResponse, PermissionAction::Create);
        Sanctum::actingAs($user);

        $response = FormResponse::create([
            'form_definition_id' => $this->publishedForm->id,
            'geography_node_id' => $this->location->id,
            'submitted_by_user_id' => $user->id,
            'channel' => 'web',
            'submitted_at' => now(),
            'values_json' => ['name' => 'Asha Devi'],
        ]);

        // Simulate the form being revised into v2.
        $v2 = $this->publishedForm->createNewDraftVersionFromPublished();
        $v2->update(['name' => 'Household Registration (v2)']);
        $v2->publish();

        $this->assertSame($this->publishedForm->id, $response->fresh()->form_definition_id);
        $this->assertSame('Household Registration', $response->fresh()->formDefinition->name);
    }
}
