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
        if (Schema::hasTable('video_items')) {
            return;
        }

        Schema::create('video_items', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('title');
            $table->text('youtube_url');
            $table->text('thumbnail_url')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_items');
    }
};
