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
        // Non-blocking heuristic duplicate flags, kept separate from beneficiaries.
        Schema::create('beneficiary_duplicate_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->cascadeOnDelete();
            $table->foreignId('matched_beneficiary_id')->constrained('beneficiaries')->cascadeOnDelete();
            $table->string('match_type'); // 'exact_id' | 'temp_id_collision' | 'attribute_heuristic'
            $table->float('match_score')->nullable(); // populated by a future fuzzy-matching engine
            $table->string('status')->default('open'); // 'open' | 'dismissed' | 'merged' (merge = Phase 2)
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'beneficiary_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiary_duplicate_flags');
    }
};
