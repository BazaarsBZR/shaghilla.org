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
        if (Schema::hasTable('site_pages')) {
            return;
        }

        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('route_name')->nullable();
            $table->string('slug')->nullable();
            $table->text('external_url')->nullable();
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->longText('content_html_ar')->nullable();
            $table->longText('content_html_en')->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->boolean('show_in_header')->default(false)->index();
            $table->boolean('show_in_footer')->default(false)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('route_name');
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_pages');
    }
};
