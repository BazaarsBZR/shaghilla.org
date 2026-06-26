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
        if (Schema::hasTable('keyword_rules')) {
            return;
        }

        Schema::create('keyword_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('keyword');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'keyword']);
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keyword_rules');
    }
};
