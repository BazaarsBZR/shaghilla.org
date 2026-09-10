<?php

namespace App\Http\Controllers;

use App\Services\PublicMoney\PublicMoneyImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class PublicMoneyImportWebhookController extends Controller
{
    public function __invoke(Request $request, PublicMoneyImporter $importer, ?string $source = null): JsonResponse
    {
        $expected = (string) env('CRON_SECRET', env('PUBLIC_MONEY_IMPORT_SECRET', ''));
        $provided = (string) $request->bearerToken();
        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            abort(404);
        }

        if (! Schema::hasTable('public_money_sources')) {
            Artisan::call('migrate', ['--force' => true]);
        }

        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\PublicMoneySourceSeeder',
            '--force' => true,
        ]);

        $allowedSources = ['ppa-awards', 'ppa-contracts', 'ppa-implementation', 'mof-budget-2026', 'mof-finance-2025'];
        if ($source !== null && ! in_array($source, $allowedSources, true)) {
            abort(404);
        }

        return response()->json([
            'ok' => true,
            'ran_at' => now()->toIso8601String(),
            'result' => $importer->import(
                $source,
                max(1, min(500, $request->integer('limit', 20))),
                $request->boolean('publish_verified'),
                max(1, min(100, $request->integer('start_page', 1))),
            ),
        ])
            ->header('Cache-Control', 'no-store');
    }
}
