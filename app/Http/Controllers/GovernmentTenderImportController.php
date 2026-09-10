<?php

namespace App\Http\Controllers;

use App\Services\PublicMoney\PpaTenderImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GovernmentTenderImportController extends Controller
{
    public function __invoke(Request $request, PpaTenderImporter $importer): JsonResponse
    {
        $secret = (string) env('CRON_SECRET');
        $provided = (string) $request->bearerToken();
        if ($secret === '' || $provided === '' || ! hash_equals($secret, $provided)) {
            abort(401);
        }

        if (! Schema::hasColumn('public_money_procurements', 'submission_deadline_at')) {
            Artisan::call('migrate', ['--force' => true]);
        }

        try {
            $result = $importer->import(
                pageLimit: min(8, max(1, $request->integer('pages', 3))),
                detailLimit: min(30, max(0, $request->integer('details', 18))),
                allPages: $request->boolean('all'),
                timeBudgetSeconds: 48,
            );

            return response()->json($result);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'The official tender source could not be refreshed. Existing verified records were preserved.',
            ], 502);
        }
    }
}
