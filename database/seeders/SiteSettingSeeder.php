<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'live_youtube_url' => '',
            'live_youtube_playlist_limit' => '12',
            'ticker_limit' => '10',
            'breaking_list_limit' => '20',
            'rss_item_limit' => '10',
            'rss_cron_enabled' => '1',
            'rss_cron_interval_minutes' => '60',
            'almanar_urgent_enabled' => '0',
            'almanar_urgent_limit' => '10',
            'almanar_urgent_secret' => '',
            'almanar_urgent_cron_enabled' => '1',
            'almanar_urgent_cron_interval_minutes' => '1',
            'breaking_ticker_poll_enabled' => '0',
            'breaking_ticker_poll_interval_seconds' => '45',
            'breaking_ticker_start_offset_seconds' => '0',
            'breaking_ticker_engine' => 'js',
            'breaking_ticker_direction' => 'left',
            'breaking_ticker_speed_px_per_sec' => '90',
            'breaking_ticker_gap_px' => '20',
            'home_hosted_videos_enabled' => '1',
            'home_hosted_videos_title_ar' => 'الفيديو',
            'home_hosted_videos_limit' => '12',
            'home_hosted_videos_layout' => 'grid',
            'home_hosted_videos_include_youtube' => '1',
            'home_hosted_videos_placeholders_enabled' => '1',
            'home_hosted_videos_placeholder_count' => '8',
            'home_hosted_videos_placeholder_title_ar' => 'رابطة الشغيلة',
            'home_hosted_videos_placeholder_youtube_url' => 'https://www.youtube.com/watch?v=QyR01ZMIIqE&t=10690s',
            'contact_page_intro_ar' => 'اكتب لنا تفاصيل طلب الخدمة وسنقوم بالمتابعة بأقرب وقت.',
            'contact_page_success_ar' => 'تم إرسال رسالتك بنجاح.',
            'contact_page_title_ar' => 'طلب الخدمة',
            'contact_form_name_label_ar' => 'الاسم',
            'contact_form_email_label_ar' => 'رقم الهاتف',
            'contact_form_subject_label_ar' => 'الموضوع',
            'contact_form_message_label_ar' => 'الرسالة',
            'contact_form_submit_label_ar' => 'إرسال',
            'membership_page_intro_ar' => 'يرجى تعبئة نموذج الانتساب والتطوّع كاملًا ثم الضغط على «تحقّق».',
            'membership_page_title_ar' => 'طلب انتساب إلى رابطة الشغيلــة',
            'membership_form_success_ar' => 'تم إرسال طلب الانتساب بنجاح.',
            'membership_form_submit_label_ar' => 'إرسال الطلب',
            'membership_form_id_label_ar' => 'صورة الهوية / جواز السفر',
            'header_weather_enabled' => '1',
            'header_weather_label_ar' => 'لبنان',
            'header_weather_lat' => '33.8938',
            'header_weather_lon' => '35.5018',
            'rss_import_secret' => 'change-me',
            'last_rss_import_at' => null,
            'last_rss_import_source' => null,
            'last_rss_import_result' => null,
            'last_rss_cron_hit_at' => null,
            'last_rss_cron_hit_ip' => null,
            'last_rss_cron_hit_method' => null,
            'last_rss_cron_hit_ua' => null,
            'last_rss_cron_hit_mode' => null,
            'last_almanar_urgent_import_at' => null,
            'last_almanar_urgent_import_source' => null,
            'last_almanar_urgent_import_result' => null,
            'last_almanar_urgent_cron_hit_at' => null,
            'last_almanar_urgent_cron_hit_ip' => null,
            'last_almanar_urgent_cron_hit_method' => null,
            'last_almanar_urgent_cron_hit_ua' => null,
            'last_almanar_urgent_cron_hit_mode' => null,
        ];

        foreach ($settings as $key => $value) {
            SiteSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
