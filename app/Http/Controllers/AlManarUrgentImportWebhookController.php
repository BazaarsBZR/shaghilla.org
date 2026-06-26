<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Services\AlManarUrgentImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlManarUrgentImportWebhookController extends Controller
{
    /**
     * Trigger Al-Manar urgent import without requiring SSH / Artisan access.
     */
    public function __invoke(Request $request, string $secret): JsonResponse
    {
        $expected = trim((string) SiteSetting::getValue('almanar_urgent_secret', ''));
        if ($expected === '') {
            $expected = trim((string) env('ALMANAR_URGENT_SECRET', ''));
        }

        if ($expected === '' || ! hash_equals($expected, $secret)) {
            abort(404);
        }

        $cronEnabled = SiteSetting::getBool('almanar_urgent_cron_enabled', true);

        SiteSetting::setValues([
            'last_almanar_urgent_cron_hit_at' => now()->toIso8601String(),
            'last_almanar_urgent_cron_hit_ip' => (string) $request->ip(),
            'last_almanar_urgent_cron_hit_method' => (string) $request->method(),
            'last_almanar_urgent_cron_hit_ua' => mb_substr((string) $request->userAgent(), 0, 500, 'UTF-8'),
        ]);

        if ($request->boolean('test')) {
            SiteSetting::setValues([
                'last_almanar_urgent_cron_hit_mode' => 'test',
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
                'last_almanar_urgent_cron_hit_mode' => 'disabled',
                'last_almanar_urgent_import_source' => 'cron_url',
            ]);

            return response()
                ->json([
                    'ok' => true,
                    'disabled' => true,
                    'cron_enabled' => false,
                    'ran_at' => now()->toIso8601String(),
                    'message' => 'Breaking scraper cron imports are disabled in the admin panel.',
                ])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                ->header('Pragma', 'no-cache');
        }

        SiteSetting::setValues([
            'last_almanar_urgent_cron_hit_mode' => 'import',
            'last_almanar_urgent_import_source' => 'cron_url',
        ]);

        $result = app(AlManarUrgentImporter::class)->importNow();

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
