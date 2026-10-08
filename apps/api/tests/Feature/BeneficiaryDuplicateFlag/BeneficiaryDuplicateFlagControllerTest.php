<?php

namespace Tests\Feature\BeneficiaryDuplicateFlag;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\Beneficiary;
use App\Models\BeneficiaryDuplicateFlag;
use App\Models\BeneficiaryType;
use App\Models\GeographyNode;
use App\Models\User;
use App\Services\Beneficiary\BeneficiaryDedupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class BeneficiaryDuplicateFlagControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    private BeneficiaryType $type;

    private GeographyNode $locationA;

    private GeographyNode $locationB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = BeneficiaryType::create(['class' => BeneficiaryType::CLASS_INDIVIDUAL, 'code' => 'women', 'label' => 'Women']);
        $this->locationA = GeographyNode::create(['level' => 0, 'label' => 'Location A']);
        $this->locationB = GeographyNode::create(['level' => 0, 'label' => 'Location B']);
    }

    private function makeFlaggedPair(GeographyNode $location): BeneficiaryDuplicateFlag
    {
        $a = Beneficiary::create([
            'beneficiary_type_id' => $this->type->id, 'geography_node_id' => $location->id,
            'name' => 'Sunita Kumari', 'dob' => '1985-03-10', 'temporary_id' => 'T-'.uniqid(),
        ]);
        $b = Beneficiary::create([
            'beneficiary_type_id' => $this->type->id, 'geography_node_id' => $location->id,
            'name' => 'Sunita Kumar', 'dob' => '1985-07-01', 'temporary_id' => 'T-'.uniqid(),
        ]);

        (new BeneficiaryDedupService)->flagPotentialDuplicates($b);

        return BeneficiaryDuplicateFlag::where('beneficiary_id', $b->id)->firstOrFail();
    }

    public function test_reviewer_only_sees_flags_within_their_location_scope(): void
    {
        $user = User::factory()->create();
        $this->grantPermissionScopedToLocations($user, ResourceType::Beneficiary, PermissionAction::View, [$this->locationA->id]);
        Sanctum::actingAs($user);

        $visibleFlag = $this->makeFlaggedPair($this->locationA);
        $this->makeFlaggedPair($this->locationB);

        $response = $this->getJson('/api/v1/beneficiary-duplicate-flags');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($visibleFlag->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_dismiss_a_flag(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::View);
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Verify);
        Sanctum::actingAs($user);

        $flag = $this->makeFlaggedPair($this->locationA);

        $response = $this->postJson("/api/v1/beneficiary-duplicate-flags/{$flag->id}/dismiss");

        $response->assertOk();
        $this->assertDatabaseHas('beneficiary_duplicate_flags', [
            'id' => $flag->id, 'status' => BeneficiaryDuplicateFlag::STATUS_DISMISSED,
        ]);
    }

    public function test_dismissed_flags_do_not_appear_in_default_open_listing(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::View);
        $this->grantPermission($user, ResourceType::Beneficiary, PermissionAction::Verify);
        Sanctum::actingAs($user);

        $flag = $this->makeFlaggedPair($this->locationA);
        $flag->dismiss($user);

        $response = $this->getJson('/api/v1/beneficiary-duplicate-flags');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
