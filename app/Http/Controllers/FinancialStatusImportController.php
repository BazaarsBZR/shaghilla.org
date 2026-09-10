<?php

namespace App\Http\Controllers;

use App\Services\PublicMoney\FinancialStatusImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class FinancialStatusImportController extends Controller
{
    public function __invoke(Request $request, FinancialStatusImporter $importer): JsonResponse
    {
        $secret = (string) env('CRON_SECRET');
        $provided = (string) $request->bearerToken();
        if ($secret === '' || $provided === '' || ! hash_equals($secret, $provided)) {
            abort(401);
        }

        try {
            return response()->json($importer->import());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'Official financial sources could not be refreshed. Last verified observations were preserved.',
            ], 502);
        }
    }
}
