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
        Schema::create('location_set_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_set_id')->constrained('location_sets')->cascadeOnDelete();
            $table->foreignId('geography_node_id')->constrained('geography_nodes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['location_set_id', 'geography_node_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_set_members');
    }
};
