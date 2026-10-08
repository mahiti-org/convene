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
        // FKs the exact form_definitions.id so responses render with their original schema.
        Schema::create('form_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid'); // client-generated on mobile
            $table->foreignId('form_definition_id')->constrained('form_definitions');
            $table->foreignId('beneficiary_id')->nullable()->constrained('beneficiaries')->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->foreignId('geography_node_id')->constrained('geography_nodes');
            $table->foreignId('submitted_by_user_id')->constrained('users');
            $table->string('channel'); // 'web' | 'mobile'
            $table->timestamp('submitted_at');
            $table->timestamp('client_created_at')->nullable();
            $table->string('sync_status')->default('synced'); // 'synced' | 'pending' (mobile queue state)
            $table->json('values_json');
            $table->timestamps();

            $table->unique('uuid');
            $table->index('form_definition_id');
            $table->index('geography_node_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_responses');
    }
};
