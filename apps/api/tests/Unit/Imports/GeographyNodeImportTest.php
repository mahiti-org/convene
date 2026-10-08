<?php

namespace Tests\Unit\Imports;

use App\Imports\GeographyNodeImport;
use App\Models\GeographyNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeographyNodeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_rows_create_the_full_hierarchy_with_parent_resolution(): void
    {
        $import = new GeographyNodeImport;

        $import->collection(collect([
            ['level' => 0, 'label' => 'State X', 'code' => 'ST-X', 'parent_code' => null],
            ['level' => 1, 'label' => 'District X1', 'code' => 'DT-X1', 'parent_code' => 'ST-X'],
            ['level' => 4, 'label' => 'Village X1A', 'code' => 'VL-X1A', 'parent_code' => 'DT-X1'],
        ]));

        $this->assertEmpty($import->errors());
        $this->assertSame(3, $import->createdCount());

        $state = GeographyNode::where('code', 'ST-X')->first();
        $district = GeographyNode::where('code', 'DT-X1')->first();
        $village = GeographyNode::where('code', 'VL-X1A')->first();

        $this->assertNull($state->parent_id);
        $this->assertSame($state->id, $district->parent_id);
        $this->assertSame($district->id, $village->parent_id);
    }

    public function test_parent_code_can_reference_a_node_already_in_the_database(): void
    {
        $existing = GeographyNode::create(['level' => 0, 'label' => 'Existing State', 'code' => 'EX-ST']);

        $import = new GeographyNodeImport;
        $import->collection(collect([
            ['level' => 1, 'label' => 'New District', 'code' => 'NEW-DT', 'parent_code' => 'EX-ST'],
        ]));

        $this->assertEmpty($import->errors());
        $this->assertSame($existing->id, GeographyNode::where('code', 'NEW-DT')->value('parent_id'));
    }

    public function test_invalid_row_aborts_the_entire_import_with_no_partial_commit(): void
    {
        $import = new GeographyNodeImport;

        $import->collection(collect([
            ['level' => 0, 'label' => 'State X', 'code' => 'ST-X', 'parent_code' => null],
            ['level' => null, 'label' => 'Bad Row', 'code' => 'BAD', 'parent_code' => null], // missing required level
            ['level' => 1, 'label' => 'District X1', 'code' => 'DT-X1', 'parent_code' => 'ST-X'],
        ]));

        $this->assertNotEmpty($import->errors());
        $this->assertArrayHasKey(3, $import->errors()); // row 3 = index 1 + 2

        // Nothing committed at all, including the otherwise-valid rows.
        $this->assertSame(0, GeographyNode::count());
        $this->assertSame(0, $import->createdCount());
    }

    public function test_unresolvable_parent_code_produces_a_row_error(): void
    {
        $import = new GeographyNodeImport;

        $import->collection(collect([
            ['level' => 1, 'label' => 'Orphan District', 'code' => 'ORPH', 'parent_code' => 'DOES-NOT-EXIST'],
        ]));

        $this->assertNotEmpty($import->errors());
        $this->assertStringContainsString('DOES-NOT-EXIST', $import->errors()[2][0]);
        $this->assertSame(0, GeographyNode::count());
    }
}
