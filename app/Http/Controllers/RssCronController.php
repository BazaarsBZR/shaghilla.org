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

        $feedOffset = max(0, min(20, (int) $request->query('feed', 0)));
        $itemOffset = max(0, min(100, (int) $request->query('offset', 0)));
        $limit = max(1, min(4, (int) $request->query('limit', 3)));

        $result = $importer->importAll(
            $limit,
            'fill_missing',
            true,
            null,
            $feedOffset,
            $itemOffset,
        );

        return response()->json([
            'ok' => true,
            'feed_offset' => $feedOffset,
            'item_offset' => $itemOffset,
            'result' => $result,
        ])->header('Cache-Control', 'no-store');
    }
}
