<?php

namespace App\Http\Controllers;

use App\Services\PublicMoney\PublicMoneyImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class PublicMoneyImportWebhookController extends Controller
{
    public function __invoke(Request $request, PublicMoneyImporter $importer): JsonResponse
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

        return response()->json(['ok' => true, 'ran_at' => now()->toIso8601String(), 'result' => $importer->import()])
            ->header('Cache-Control', 'no-store');
    }
}
