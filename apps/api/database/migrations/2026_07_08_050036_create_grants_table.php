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
        // Role lives on the Grant, not the User, so roles can differ per project.
        Schema::create('grants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            // Scope: project/sub_project have no FK yet (M6 builds `projects`/`sub_projects`).
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('sub_project_id')->nullable();
            $table->foreignId('location_set_id')->nullable()->constrained('location_sets')->nullOnDelete();
            $table->string('scope_type'); // 'explicit' | 'derived'
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable(); // grants are revoked, never hard-deleted
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
            $table->index(['project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grants');
    }
};
