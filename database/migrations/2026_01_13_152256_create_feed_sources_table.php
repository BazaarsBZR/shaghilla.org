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
        if (Schema::hasTable('feed_sources')) {
            return;
        }

        Schema::create('feed_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('url');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('etag')->nullable();
            $table->string('last_modified')->nullable();
            $table->foreignId('default_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feed_sources');
    }
};
