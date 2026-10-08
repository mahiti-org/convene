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
        // Keyed by form_code, not a version row, so it always targets the current published version.
        Schema::create('form_definition_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('form_code');
            $table->string('assignable_type'); // 'user' | 'role'
            $table->unsignedBigInteger('assignable_id');
            $table->timestamps();

            // Explicit short names: MySQL's 64-char identifier limit rejects Laravel's
            // auto-generated name for this column combination.
            $table->unique(['form_code', 'assignable_type', 'assignable_id'], 'form_def_assignments_unique');
            $table->index(['assignable_type', 'assignable_id'], 'form_def_assignments_assignable_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_definition_assignments');
    }
};
