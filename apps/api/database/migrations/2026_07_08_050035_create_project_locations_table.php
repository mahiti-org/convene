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
        // project_id FK is added later by add_project_foreign_keys_to_existing_tables (projects
        // lands in M6).
        Schema::create('project_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->foreignId('geography_node_id')->constrained('geography_nodes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'geography_node_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_locations');
    }
};
