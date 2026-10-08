<?php

namespace Tests\Unit\Services;

use App\Models\Beneficiary;
use App\Models\BeneficiaryDuplicateFlag;
use App\Models\BeneficiaryType;
use App\Models\GeographyNode;
use App\Services\Beneficiary\BeneficiaryDedupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiaryDedupServiceTest extends TestCase
{
    use RefreshDatabase;

    private BeneficiaryType $type;

    private GeographyNode $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = BeneficiaryType::create([
            'class' => BeneficiaryType::CLASS_INDIVIDUAL, 'code' => 'women', 'label' => 'Women',
        ]);
        $this->location = GeographyNode::create(['level' => 0, 'label' => 'Test Location']);
    }

    public function test_find_exact_duplicate_returns_null_when_no_government_id_given(): void
    {
        $service = new BeneficiaryDedupService;

        $this->assertNull($service->findExactDuplicate(null, null));
    }

    public function test_find_exact_duplicate_finds_a_match_regardless_of_location_scope(): void
    {
        Beneficiary::create([
            'beneficiary_type_id' => $this->type->id,
            'geography_node_id' => $this->location->id,
            'name' => 'Existing Person',
            'government_id_type' => 'aadhaar',
            'government_id_value' => '123412341234',
        ]);

        $service = new BeneficiaryDedupService;
        $match = $service->findExactDuplicate('aadhaar', '123412341234');

        $this->assertNotNull($match);
        $this->assertSame('Existing Person', $match->name);
    }

    public function test_find_potential_duplicates_matches_similar_name_and_same_dob_year(): void
    {
        $existing = Beneficiary::create([
            'beneficiary_type_id' => $this->type->id,
            'geography_node_id' => $this->location->id,
            'name' => 'Sunita Kumari',
            'dob' => '1985-03-10',
            'temporary_id' => 'T-1',
        ]);

        $service = new BeneficiaryDedupService;
        $matches = $service->findPotentialDuplicates('Sunita Kumar', '1985-11-20', $this->location->id);

        $this->assertCount(1, $matches);
        $this->assertSame($existing->id, $matches->first()->id);
    }

    public function test_find_potential_duplicates_excludes_different_dob_year(): void
    {
        Beneficiary::create([
            'beneficiary_type_id' => $this->type->id,
            'geography_node_id' => $this->location->id,
            'name' => 'Sunita Kumari',
            'dob' => '1985-03-10',
            'temporary_id' => 'T-1',
        ]);

        $service = new BeneficiaryDedupService;
        $matches = $service->findPotentialDuplicates('Sunita Kumari', '1990-03-10', $this->location->id);

        $this->assertCount(0, $matches);
    }

    public function test_find_potential_duplicates_excludes_a_different_geography_node(): void
    {
        $otherLocation = GeographyNode::create(['level' => 0, 'label' => 'Other Location']);

        Beneficiary::create([
            'beneficiary_type_id' => $this->type->id,
            'geography_node_id' => $otherLocation->id,
            'name' => 'Sunita Kumari',
            'dob' => '1985-03-10',
            'temporary_id' => 'T-1',
        ]);

        $service = new BeneficiaryDedupService;
        $matches = $service->findPotentialDuplicates('Sunita Kumari', '1985-03-10', $this->location->id);

        $this->assertCount(0, $matches);
    }

    public function test_find_potential_duplicates_excludes_the_given_beneficiary_itself(): void
    {
        $beneficiary = Beneficiary::create([
            'beneficiary_type_id' => $this->type->id,
            'geography_node_id' => $this->location->id,
            'name' => 'Sunita Kumari',
            'dob' => '1985-03-10',
            'temporary_id' => 'T-1',
        ]);

        $service = new BeneficiaryDedupService;
        $matches = $service->findPotentialDuplicates(
            'Sunita Kumari', '1985-03-10', $this->location->id, $beneficiary->id,
        );

        $this->assertCount(0, $matches);
    }

    public function test_flag_potential_duplicates_creates_a_flag_row(): void
    {
        $existing = Beneficiary::create([
            'beneficiary_type_id' => $this->type->id,
            'geography_node_id' => $this->location->id,
            'name' => 'Sunita Kumari',
            'dob' => '1985-03-10',
            'temporary_id' => 'T-1',
        ]);
        $new = Beneficiary::create([
            'beneficiary_type_id' => $this->type->id,
            'geography_node_id' => $this->location->id,
            'name' => 'Sunita Kumar',
            'dob' => '1985-07-01',
            'temporary_id' => 'T-2',
        ]);

        $service = new BeneficiaryDedupService;
        $service->flagPotentialDuplicates($new);

        $this->assertDatabaseHas('beneficiary_duplicate_flags', [
            'beneficiary_id' => $new->id,
            'matched_beneficiary_id' => $existing->id,
            'match_type' => 'attribute_heuristic',
            'status' => BeneficiaryDuplicateFlag::STATUS_OPEN,
        ]);
    }

    public function test_flag_potential_duplicates_does_not_duplicate_an_existing_open_flag(): void
    {
        $existing = Beneficiary::create([
            'beneficiary_type_id' => $this->type->id,
            'geography_node_id' => $this->location->id,
            'name' => 'Sunita Kumari',
            'dob' => '1985-03-10',
            'temporary_id' => 'T-1',
        ]);
        $new = Beneficiary::create([
            'beneficiary_type_id' => $this->type->id,
            'geography_node_id' => $this->location->id,
            'name' => 'Sunita Kumar',
            'dob' => '1985-07-01',
            'temporary_id' => 'T-2',
        ]);

        $service = new BeneficiaryDedupService;
        $service->flagPotentialDuplicates($new);
        $service->flagPotentialDuplicates($new); // run twice

        $this->assertSame(1, BeneficiaryDuplicateFlag::where('beneficiary_id', $new->id)
            ->where('matched_beneficiary_id', $existing->id)
            ->count());
    }
}
