<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            KeywordRuleSeeder::class,
            FeedSourceSeeder::class,
            SiteSettingSeeder::class,
            SitePageSeeder::class,
            SharePlatformSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
