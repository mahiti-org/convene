<?php

namespace Tests\Feature\GeographyNode;

use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\GeographyNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\GrantsPermissions;
use Tests\TestCase;

class GeographyNodeImportControllerTest extends TestCase
{
    use GrantsPermissions;
    use RefreshDatabase;

    public function test_valid_csv_upload_creates_nodes_end_to_end(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::Import);
        Sanctum::actingAs($user);

        $csv = "level,label,code,parent_code\n0,State X,ST-X,\n1,District X1,DT-X1,ST-X\n";
        $file = UploadedFile::fake()->createWithContent('geography.csv', $csv);

        $response = $this->postJson('/api/v1/geography-nodes/import', ['file' => $file]);

        $response->assertOk();
        $response->assertJson(['created' => 2]);
        $this->assertSame(2, GeographyNode::count());
        $this->assertDatabaseHas('geography_nodes', ['code' => 'DT-X1', 'parent_id' => GeographyNode::where('code', 'ST-X')->value('id')]);
    }

    public function test_invalid_csv_upload_returns_error_report_and_creates_nothing(): void
    {
        $user = User::factory()->create();
        $this->grantPermission($user, ResourceType::GeographyNode, PermissionAction::Import);
        Sanctum::actingAs($user);

        $csv = "level,label,code,parent_code\n0,State X,ST-X,\n,Bad Row,BAD,\n";
        $file = UploadedFile::fake()->createWithContent('geography.csv', $csv);

        $response = $this->postJson('/api/v1/geography-nodes/import', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertSame(0, GeographyNode::count());
    }

    public function test_user_without_import_permission_is_denied(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $csv = "level,label,code,parent_code\n0,State X,ST-X,\n";
        $file = UploadedFile::fake()->createWithContent('geography.csv', $csv);

        $this->postJson('/api/v1/geography-nodes/import', ['file' => $file])->assertForbidden();
        $this->assertSame(0, GeographyNode::count());
    }
}
