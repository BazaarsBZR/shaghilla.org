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
        if (Schema::hasTable('articles')) {
            return;
        }

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_source_id')->nullable()->constrained('feed_sources')->nullOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->text('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->text('canonical_url')->nullable();
            $table->char('canonical_url_hash', 64)->nullable();
            $table->text('guid')->nullable();
            $table->char('guid_hash', 64);
            $table->text('image_url')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('imported_at')->useCurrent()->index();
            $table->boolean('is_breaking')->default(false)->index();
            $table->string('language', 5)->default('ar');
            $table->string('status')->default('published')->index();
            $table->timestamps();

            $table->unique(['feed_source_id', 'guid_hash']);
            $table->unique('canonical_url_hash');
            $table->index(['category_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
