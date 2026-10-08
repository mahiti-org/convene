<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Minimal shape for M1 (referenced by location_sets/user_locations/project_locations).
        // Full hierarchy configuration, bulk-load, and deactivate-with-children-guard land in M2.
        Schema::create('geography_nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('geography_nodes')->nullOnDelete();
            $table->unsignedTinyInteger('level'); // 0 = root (e.g. State), increasing downward
            $table->string('label');
            $table->string('code')->nullable();
            $table->softDeletes();
            $table->string('deactivation_reason')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['parent_id', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geography_nodes');
    }
};
