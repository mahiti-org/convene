<?php

namespace Tests\Feature\Beneficiary;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\Beneficiary;
use App\Models\BeneficiaryType;
use App\Models\GeographyNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class BeneficiaryControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    private BeneficiaryType $householdType;

    private BeneficiaryType $individualType;

    private GeographyNode $locationA;

    private GeographyNode $locationB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->householdType = BeneficiaryType::create([
            'class' => BeneficiaryType::CLASS_INSTITUTIONAL, 'code' => 'household', 'label' => 'Household',
        ]);
        $this->individualType = BeneficiaryType::create([
            'class' => BeneficiaryType::CLASS_INDIVIDUAL, 'code' => 'women', 'label' => 'Women',
        ]);
        $this->locationA = GeographyNode::create(['level' => 0, 'label' => 'Location A']);
        $this->locationB = GeographyNode::create(['level' => 0, 'label' => 'Location B']);
    }

    public function test_user_without_create_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Asha Devi',
            'temporary_id' => 'TEMP-001',
        ]);

        $response->assertForbidden();
    }

    public function test_can_create_an_individual_beneficiary_with_temporary_id(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Create);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Asha Devi',
            'temporary_id' => 'TEMP-001',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('beneficiaries', ['name' => 'Asha Devi', 'temporary_id' => 'TEMP-001']);
    }

    public function test_household_permission_is_checked_separately_from_beneficiary_permission(): void
    {
        $user = User::factory()->create();
        // Only granted generic Beneficiary create, not Household.
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Create);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->householdType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Devi Household',
            'temporary_id' => 'HH-TEMP-001',
        ]);

        $response->assertForbidden();

        $this->grantPermission($user, ResourceType::Household, PermissionAction::Create);

        $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->householdType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Devi Household',
            'temporary_id' => 'HH-TEMP-001',
        ])->assertCreated();
    }

    public function test_neither_government_id_nor_temporary_id_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Create);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'No ID Person',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('government_id_value');
    }

    public function test_exact_government_id_duplicate_is_hard_blocked(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Create);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Original Person',
            'government_id_type' => 'aadhaar',
            'government_id_value' => '111122223333',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationB->id,
            'name' => 'Different Name Entirely',
            'government_id_type' => 'aadhaar',
            'government_id_value' => '111122223333',
        ]);

        $response->assertStatus(409);
        $this->assertSame(1, Beneficiary::where('government_id_value', '111122223333')->count());
    }

    public function test_household_tagging_requires_an_institutional_household_id(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Create);
        Sanctum::actingAs($user);

        $notAHousehold = Beneficiary::create([
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Some Individual',
            'temporary_id' => 'IND-1',
        ]);

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Child of Some Individual',
            'temporary_id' => 'IND-2',
            'household_id' => $notAHousehold->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_household_tagging_succeeds_for_an_institutional_household(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Create);
        $this->grantPermission($user, ResourceType::Household, PermissionAction::Create);
        Sanctum::actingAs($user);

        $household = Beneficiary::create([
            'beneficiary_type_id' => $this->householdType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Devi Household',
            'temporary_id' => 'HH-1',
        ]);

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Asha Devi',
            'temporary_id' => 'IND-1',
            'household_id' => $household->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('beneficiaries', ['name' => 'Asha Devi', 'household_id' => $household->id]);
    }

    public function test_similar_name_and_dob_in_same_location_is_flagged_not_blocked(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Create);
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::View);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Sunita Kumari',
            'dob' => '1990-05-15',
            'temporary_id' => 'T-1',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Sunita Kumar', // near-identical name
            'dob' => '1990-08-02', // same year, different day
            'temporary_id' => 'T-2',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('potential_duplicate_flags', 1);
        $this->assertSame(2, Beneficiary::count()); // neither record is blocked
        $this->assertDatabaseHas('beneficiary_duplicate_flags', ['status' => 'open']);
    }

    public function test_dissimilar_name_in_same_location_is_not_flagged(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Create);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Sunita Kumari',
            'dob' => '1990-05-15',
            'temporary_id' => 'T-1',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Rekha Devi',
            'dob' => '1990-05-15',
            'temporary_id' => 'T-2',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('potential_duplicate_flags', 0);
    }

    public function test_user_scoped_to_one_location_cannot_see_beneficiary_in_another_location(): void
    {
        $user = User::factory()->create();
        $this->grantPermissionScopedToLocations(
            $user, ResourceType::Beneficiary, PermissionAction::View, [$this->locationA->id],
        );
        Sanctum::actingAs($user);

        Beneficiary::create([
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Visible Person',
            'temporary_id' => 'T-A',
        ]);
        Beneficiary::create([
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationB->id,
            'name' => 'Hidden Person',
            'temporary_id' => 'T-B',
        ]);

        $response = $this->getJson('/api/v1/beneficiaries');

        $response->assertOk();
        $names = collect($response->json('data.data'))->pluck('name')->all();
        $this->assertContains('Visible Person', $names);
        $this->assertNotContains('Hidden Person', $names);
    }

    public function test_org_wide_grant_sees_beneficiaries_across_all_locations(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::View); // org-wide, no location_set
        Sanctum::actingAs($user);

        Beneficiary::create([
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Person A',
            'temporary_id' => 'T-A',
        ]);
        Beneficiary::create([
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationB->id,
            'name' => 'Person B',
            'temporary_id' => 'T-B',
        ]);

        $response = $this->getJson('/api/v1/beneficiaries');

        $response->assertOk();
        $names = collect($response->json('data.data'))->pluck('name')->all();
        $this->assertContains('Person A', $names);
        $this->assertContains('Person B', $names);
    }

    public function test_creating_a_beneficiary_outside_the_users_granted_location_is_denied(): void
    {
        $user = User::factory()->create();
        $this->grantPermissionScopedToLocations(
            $user, ResourceType::Beneficiary, PermissionAction::Create, [$this->locationA->id],
        );
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationB->id,
            'name' => 'Out Of Scope Person',
            'temporary_id' => 'T-1',
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Beneficiary::count());
    }

    public function test_deactivate_requires_web_channel(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $beneficiary = Beneficiary::create([
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'To Deactivate',
            'temporary_id' => 'T-1',
        ]);

        $this->withHeader('X-Channel', 'mobile')
            ->postJson("/api/v1/beneficiaries/{$beneficiary->id}/deactivate", ['reason' => 'test'])
            ->assertForbidden();

        $this->withHeader('X-Channel', 'web')
            ->postJson("/api/v1/beneficiaries/{$beneficiary->id}/deactivate", ['reason' => 'Duplicate record'])
            ->assertOk();

        $this->assertSoftDeleted('beneficiaries', ['id' => $beneficiary->id]);
    }

    public function test_search_reports_exact_duplicate_without_exposing_record_details(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Create);
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Search);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/beneficiaries', [
            'beneficiary_type_id' => $this->individualType->id,
            'geography_node_id' => $this->locationA->id,
            'name' => 'Existing Person',
            'government_id_type' => 'aadhaar',
            'government_id_value' => '999988887777',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/beneficiaries/search?government_id_type=aadhaar&government_id_value=999988887777');

        $response->assertOk();
        $response->assertJsonPath('exact_duplicate_exists', true);
        $response->assertJsonMissing(['name' => 'Existing Person']); // no record details leaked
    }
}
