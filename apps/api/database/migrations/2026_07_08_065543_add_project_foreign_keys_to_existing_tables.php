<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds FK constraints deferred until projects and sub_projects existed.
     */
    public function up(): void
    {
        Schema::table('grants', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->foreign('sub_project_id')->references('id')->on('sub_projects')->nullOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
        });

        Schema::table('project_locations', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grants', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['sub_project_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('project_locations', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
    }
};
