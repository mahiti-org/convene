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
        // attribute_schema is a plain, unversioned JSON list of {key,label,data_type,required}.
        Schema::create('beneficiary_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('class'); // 'institutional' | 'individual'
            $table->string('code')->unique();
            $table->string('label');
            $table->json('attribute_schema')->nullable();
            $table->boolean('is_system')->default(false);
            $table->softDeletes();
            $table->string('deactivation_reason')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiary_types');
    }
};
