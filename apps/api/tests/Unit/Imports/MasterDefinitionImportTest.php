<?php

namespace Tests\Unit\Imports;

use App\Imports\MasterDefinitionImport;
use App\Models\MasterDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDefinitionImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_rows_are_created(): void
    {
        $import = new MasterDefinitionImport;

        $import->collection(collect([
            ['category' => 'gender', 'code' => 'male', 'label' => 'Male', 'sort_order' => 1],
            ['category' => 'gender', 'code' => 'female', 'label' => 'Female', 'sort_order' => 2],
        ]));

        $this->assertEmpty($import->errors());
        $this->assertSame(2, $import->createdCount());
        $this->assertDatabaseHas('master_definitions', ['category' => 'gender', 'code' => 'male']);
    }

    public function test_duplicate_code_within_file_is_rejected_without_partial_commit(): void
    {
        $import = new MasterDefinitionImport;

        $import->collection(collect([
            ['category' => 'gender', 'code' => 'male', 'label' => 'Male'],
            ['category' => 'gender', 'code' => 'male', 'label' => 'Male again'],
        ]));

        $this->assertNotEmpty($import->errors());
        $this->assertSame(0, MasterDefinition::count());
    }

    public function test_duplicate_against_existing_database_row_is_rejected(): void
    {
        MasterDefinition::create(['category' => 'gender', 'code' => 'male', 'label' => 'Male']);

        $import = new MasterDefinitionImport;
        $import->collection(collect([
            ['category' => 'gender', 'code' => 'male', 'label' => 'Male duplicate'],
        ]));

        $this->assertNotEmpty($import->errors());
        $this->assertSame(1, MasterDefinition::count()); // only the pre-existing one
    }

    public function test_missing_required_field_produces_row_error(): void
    {
        $import = new MasterDefinitionImport;

        $import->collection(collect([
            ['category' => 'gender', 'code' => null, 'label' => 'Missing code'],
        ]));

        $this->assertNotEmpty($import->errors());
        $this->assertSame(0, MasterDefinition::count());
    }
}
