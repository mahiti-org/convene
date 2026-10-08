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
        // Append-only, one row per edit.
        Schema::create('form_response_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_response_id')->constrained('form_responses')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->json('diff_json'); // {before: {...}, after: {...}} for the changed widget keys
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index('form_response_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_response_histories');
    }
};
