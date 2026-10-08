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
        // Drafts edit in place; editing a published form adds a new version row.
        Schema::create('form_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code'); // stable across versions; NOT unique alone, see (code, version)
            $table->string('name');
            $table->foreignId('beneficiary_type_id')->nullable()->constrained('beneficiary_types')->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->unsignedTinyInteger('geography_level')->nullable();
            $table->string('periodicity'); // 'one_time' | 'monthly' | 'quarterly' | 'half_yearly' | 'annual' | 'ad_hoc'
            $table->string('status')->default('draft'); // 'draft' | 'published' | 'deprecated'
            $table->unsignedInteger('version')->default(1);
            $table->json('schema_json')->nullable();
            $table->foreignId('predecessor_id')->nullable()->constrained('form_definitions')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->string('deactivation_reason')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['code', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_definitions');
    }
};
