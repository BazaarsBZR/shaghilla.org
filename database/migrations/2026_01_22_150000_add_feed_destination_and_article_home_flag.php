<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_sources', function (Blueprint $table): void {
            if (! Schema::hasColumn('feed_sources', 'destination')) {
                $table->string('destination', 20)->default('both');
                $table->index(['destination']);
            }
        });

        Schema::table('articles', function (Blueprint $table): void {
            if (! Schema::hasColumn('articles', 'show_on_home')) {
                $table->boolean('show_on_home')->default(true)->index();
            }

            if (! Schema::hasColumn('articles', 'is_breaking_locked')) {
                $table->boolean('is_breaking_locked')->default(false)->index();
            }

            if (! Schema::hasColumn('articles', 'show_on_home_locked')) {
                $table->boolean('show_on_home_locked')->default(false)->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('feed_sources', function (Blueprint $table): void {
            if (Schema::hasColumn('feed_sources', 'destination')) {
                $table->dropIndex(['destination']);
                $table->dropColumn('destination');
            }
        });

        Schema::table('articles', function (Blueprint $table): void {
            if (Schema::hasColumn('articles', 'show_on_home')) {
                $table->dropIndex(['show_on_home']);
                $table->dropColumn('show_on_home');
            }

            if (Schema::hasColumn('articles', 'is_breaking_locked')) {
                $table->dropIndex(['is_breaking_locked']);
                $table->dropColumn('is_breaking_locked');
            }

            if (Schema::hasColumn('articles', 'show_on_home_locked')) {
                $table->dropIndex(['show_on_home_locked']);
                $table->dropColumn('show_on_home_locked');
            }
        });
    }
};

