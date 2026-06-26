<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\RssImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RssImportWebhookController extends Controller
{
    /**
     * Trigger RSS import without requiring SSH / Artisan access.
     */
    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $expected = SiteSetting::getValue('rss_import_secret', (string) env('RSS_IMPORT_SECRET', ''));

        if ($expected === '' || ! hash_equals($expected, $secret)) {
            abort(404);
        }

        $cronEnabled = SiteSetting::getBool('rss_cron_enabled', true);

        SiteSetting::setValues([
            'last_rss_cron_hit_at' => now()->toIso8601String(),
            'last_rss_cron_hit_ip' => (string) $request->ip(),
            'last_rss_cron_hit_method' => (string) $request->method(),
            'last_rss_cron_hit_ua' => mb_substr((string) $request->userAgent(), 0, 500, 'UTF-8'),
        ]);

        if ($request->boolean('test')) {
            SiteSetting::setValues([
                'last_rss_cron_hit_mode' => 'test',
            ]);

            return response()
                ->json([
                    'ok' => true,
                    'test' => true,
                    'cron_enabled' => $cronEnabled,
                    'ran_at' => now()->toIso8601String(),
                    'message' => 'Cron URL is reachable. Import was not executed (test mode).',
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        if (! $cronEnabled) {
            SiteSetting::setValues([
                'last_rss_cron_hit_mode' => 'disabled',
                'last_rss_import_source' => 'cron_url',
            ]);

            return response()
                ->json([
                    'ok' => true,
                    'disabled' => true,
                    'cron_enabled' => false,
                    'ran_at' => now()->toIso8601String(),
                    'message' => 'RSS cron imports are disabled in the admin panel.',
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        $force = $request->boolean('force');
        $requireImage = $request->boolean('require_image');
        $behavior = trim((string) $request->input('behavior', ''));
        $behaviorOverride = $behavior !== '' ? $behavior : null;

        SiteSetting::setValues([
            'last_rss_cron_hit_mode' => 'import',
            'last_rss_import_source' => 'cron_url',
        ]);

        $result = app(RssImporter::class)->importAll(
            null,
            $behaviorOverride,
            $force ? true : null,
            $requireImage ? true : null,
        );

        return response()
            ->json([
                'ok' => true,
                'ran_at' => now()->toIso8601String(),
                'cron_enabled' => true,
                'result' => $result,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
