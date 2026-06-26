<?php

namespace Database\Seeders;

use App\Models\SharePlatform;
use Illuminate\Database\Seeder;

class SharePlatformSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'name' => 'WhatsApp',
                'slug' => 'whatsapp',
                'icon' => 'fa-whatsapp',
                'share_url_template' => 'https://wa.me/?text={title}%20{url}',
                'use_native_share' => false,
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'X',
                'slug' => 'x',
                'icon' => 'fa-x-twitter',
                'share_url_template' => 'https://twitter.com/intent/tweet?url={url}&text={title}',
                'use_native_share' => false,
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'Instagram',
                'slug' => 'instagram',
                'icon' => 'fa-instagram',
                'share_url_template' => null,
                'use_native_share' => true,
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'Facebook',
                'slug' => 'facebook',
                'icon' => 'fa-facebook-f',
                'share_url_template' => 'https://www.facebook.com/sharer/sharer.php?u={url}',
                'use_native_share' => false,
                'sort_order' => 40,
                'is_active' => true,
            ],
        ];

        foreach ($defaults as $row) {
            SharePlatform::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row,
            );
        }
    }
}
