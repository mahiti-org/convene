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
        // (resource_type, action, channel) tuples.
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('resource_type');
            $table->string('action');
            $table->string('channel'); // web | mobile | any
            $table->timestamps();

            $table->unique(['resource_type', 'action', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
