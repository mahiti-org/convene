<?php

namespace Tests\Feature\BeneficiaryType;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\BeneficiaryType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class BeneficiaryTypeControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    public function test_user_without_create_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/beneficiary-types', [
            'class' => BeneficiaryType::CLASS_INDIVIDUAL, 'code' => 'farmers', 'label' => 'Farmers',
        ])->assertForbidden();
    }

    public function test_can_create_a_beneficiary_type_with_attribute_schema(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::BeneficiaryType, PermissionAction::Create);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/beneficiary-types', [
            'class' => BeneficiaryType::CLASS_INDIVIDUAL,
            'code' => 'farmers',
            'label' => 'Farmers',
            'attribute_schema' => [
                ['key' => 'land_holding_acres', 'label' => 'Land Holding (acres)', 'data_type' => 'decimal', 'required' => false],
            ],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('beneficiary_types', ['code' => 'farmers']);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::BeneficiaryType, PermissionAction::Create);
        Sanctum::actingAs($user);

        BeneficiaryType::create(['class' => BeneficiaryType::CLASS_INDIVIDUAL, 'code' => 'farmers', 'label' => 'Farmers']);

        $this->postJson('/api/v1/beneficiary-types', [
            'class' => BeneficiaryType::CLASS_INDIVIDUAL, 'code' => 'farmers', 'label' => 'Farmers (dup)',
        ])->assertStatus(422);
    }

    public function test_filter_by_class(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::BeneficiaryType, PermissionAction::View);
        Sanctum::actingAs($user);

        BeneficiaryType::create(['class' => BeneficiaryType::CLASS_INSTITUTIONAL, 'code' => 'household', 'label' => 'Household']);
        BeneficiaryType::create(['class' => BeneficiaryType::CLASS_INDIVIDUAL, 'code' => 'women', 'label' => 'Women']);

        $response = $this->getJson('/api/v1/beneficiary-types?class=individual');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertSame(['women'], $codes);
    }

    public function test_deactivate_a_beneficiary_type(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::BeneficiaryType, PermissionAction::SoftDelete, Channel::Web);
        Sanctum::actingAs($user);

        $type = BeneficiaryType::create(['class' => BeneficiaryType::CLASS_INDIVIDUAL, 'code' => 'farmers', 'label' => 'Farmers']);

        $this->postJson("/api/v1/beneficiary-types/{$type->id}/deactivate", ['reason' => 'Unused'])
            ->assertOk();

        $this->assertSoftDeleted('beneficiary_types', ['id' => $type->id]);
    }
}
