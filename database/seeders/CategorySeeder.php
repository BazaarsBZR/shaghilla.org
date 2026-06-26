<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'slug' => 'lebanon',
                'name_ar' => 'لبنان',
                'name_en' => 'Lebanon',
                'sort_order' => 1,
                'is_system' => true,
            ],
            [
                'slug' => 'workers',
                'name_ar' => 'عمال',
                'name_en' => 'Workers',
                'sort_order' => 2,
                'is_system' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category,
            );
        }
    }
}
