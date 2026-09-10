<?php

namespace App\Http\Controllers;

use App\Services\RssImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RssCronController extends Controller
{
    public function __invoke(Request $request, RssImporter $importer): JsonResponse
    {
        $expected = (string) env('CRON_SECRET');
        $provided = (string) $request->bearerToken();

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401)
                ->header('Cache-Control', 'no-store');
        }

        $result = $importer->importAll(20, 'fill_missing', true, null);

        return response()->json([
            'ok' => true,
            'result' => $result,
        ])->header('Cache-Control', 'no-store');
    }
}
