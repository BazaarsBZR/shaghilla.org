<?php

namespace Database\Seeders;

use App\Models\SitePage;
use Illuminate\Database\Seeder;

class SitePageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'type' => SitePage::TYPE_ROUTE,
                'route_name' => 'home',
                'title_ar' => 'الرئيسية',
                'title_en' => 'Home',
                'show_in_header' => true,
                'show_in_footer' => true,
                'sort_order' => 1,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'type' => SitePage::TYPE_ROUTE,
                'route_name' => 'live',
                'title_ar' => 'مباشر',
                'title_en' => 'Live',
                'show_in_header' => true,
                'show_in_footer' => true,
                'sort_order' => 2,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'type' => SitePage::TYPE_ROUTE,
                'route_name' => 'membership',
                'title_ar' => 'انتساب',
                'title_en' => 'Membership',
                'show_in_header' => true,
                'show_in_footer' => true,
                'sort_order' => 3,
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'type' => SitePage::TYPE_ROUTE,
                'route_name' => 'contact',
                'title_ar' => 'طلب الخدمة',
                'title_en' => 'Contact us',
                'show_in_header' => true,
                'show_in_footer' => true,
                'sort_order' => 4,
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            SitePage::updateOrCreate(
                ['route_name' => $item['route_name']],
                $item,
            );
        }
    }
}

