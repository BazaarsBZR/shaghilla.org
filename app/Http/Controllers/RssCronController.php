<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\RssImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

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

        $normalizedHeadlines = 0;
        if (Schema::hasColumn('articles', 'show_on_home')) {
            $placement = ['show_on_home' => false];
            if (Schema::hasColumn('articles', 'is_breaking_locked')) {
                $placement['is_breaking_locked'] = true;
            }
            if (Schema::hasColumn('articles', 'show_on_home_locked')) {
                $placement['show_on_home_locked'] = true;
            }

            $normalizedHeadlines += Article::query()
                ->whereNull('feed_source_id')
                ->whereNull('canonical_url')
                ->where('guid', 'like', 'https://almanar.com.lb/%')
                ->update($placement);

            $normalizedHeadlines += Article::query()
                ->whereHas('feedSource', fn ($feed) => $feed->where('destination', 'breaking'))
                ->update($placement);

            Cache::forget('news.home.hero');
            Cache::forget('news.home.latest');
        }

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
            'normalized_headlines' => $normalizedHeadlines,
            'result' => $result,
        ])->header('Cache-Control', 'no-store');
    }
}
