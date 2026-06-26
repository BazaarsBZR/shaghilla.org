<?php

namespace App\Services;

use App\Models\Article;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

class BreakingNewsLimiter
{
    /**
     * Ensures only the newest N articles remain flagged as breaking.
     *
     * @return int Number of articles un-flagged from breaking.
     */
    public function keepLatest(?int $limitOverride = null): int
    {
        $limit = $limitOverride ?? SiteSetting::getInt('ticker_limit', 10);
        $limit = max(1, min(50, (int) $limit));

        $keepIds = Article::query()
            ->where('status', 'published')
            ->where('is_breaking', true)
            ->orderByDesc('imported_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        if ($keepIds === []) {
            Cache::forget('news.breaking.ticker');
            Cache::forget('news.breaking.live');
            Cache::forget('api.breaking.v1.limit_'.$limit);
            return 0;
        }

        $updated = Article::query()
            ->where('is_breaking', true)
            ->whereNotIn('id', $keepIds)
            ->update(['is_breaking' => false]);

        Cache::forget('news.breaking.ticker');
        Cache::forget('news.breaking.live');
        Cache::forget('api.breaking.v1.limit_'.$limit);

        return (int) $updated;
    }
}
