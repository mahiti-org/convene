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
        // The government-ID unique index blocks exact duplicates; NULLs never collide.
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique(); // client-generated on mobile
            $table->foreignId('beneficiary_type_id')->constrained('beneficiary_types');
            $table->foreignId('household_id')->nullable()->constrained('beneficiaries')->nullOnDelete();
            $table->foreignId('geography_node_id')->constrained('geography_nodes');
            $table->string('name');
            $table->date('dob')->nullable();
            $table->string('gender')->nullable(); // references a master_definitions code (category=gender), not FK-enforced
            $table->string('government_id_type')->nullable();
            $table->string('government_id_value')->nullable();
            $table->boolean('government_id_checksum_valid')->default(false);
            $table->string('temporary_id')->nullable();
            $table->json('attributes_json')->nullable();
            $table->boolean('consent_given')->default(false);
            $table->timestamp('consent_captured_at')->nullable();
            $table->string('created_channel')->default('web'); // 'web' | 'mobile'
            $table->timestamp('client_created_at')->nullable();
            $table->softDeletes();
            $table->string('deactivation_reason')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['government_id_type', 'government_id_value']);
            $table->index('geography_node_id');
            $table->index('household_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
