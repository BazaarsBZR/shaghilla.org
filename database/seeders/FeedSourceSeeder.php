<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FeedSource;
use Illuminate\Database\Seeder;

class FeedSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultCategoryId = Category::where('slug', 'lebanon')->value('id');

        $sources = [
            [
                'name' => 'Lebanon24 - Lebanon',
                'url' => 'https://www.lebanon24.com/Rss/News/1/لبنان',
                'is_active' => true,
                'default_category_id' => $defaultCategoryId,
            ],
            [
                'name' => 'Lebanon24 - Breaking',
                'url' => 'https://www.lebanon24.com/Rss/News/23/أخبار-عاجلة',
                'is_active' => true,
                'default_category_id' => $defaultCategoryId,
            ],
            [
                'name' => 'NNA (English)',
                'url' => 'https://www.nna-leb.gov.lb/en/rss',
                'is_active' => true,
                'default_category_id' => $defaultCategoryId,
            ],
        ];

        foreach ($sources as $source) {
            FeedSource::updateOrCreate(
                ['url' => $source['url']],
                $source,
            );
        }
    }
}
