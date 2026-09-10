<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BreakingApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', SiteSetting::getInt('ticker_limit', 10));
        $limit = max(1, min(50, $limit));

        $articles = Cache::remember(
            'api.breaking.v3.limit_'.$limit,
            now()->addSeconds(30),
            function () use ($limit) {
                $items = Article::query()
                    ->with('feedSource:id,destination')
                    ->where('status', 'published')
                    ->where('is_breaking', true)
                    ->orderByDesc('imported_at')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit($limit)
                    ->get(['id', 'feed_source_id', 'slug', 'title', 'canonical_url', 'guid', 'published_at']);

                return $items->isNotEmpty()
                    ? $items
                        : Article::query()
                            ->with('feedSource:id,destination')
                        ->where('status', 'published')
                        ->orderByDesc('published_at')
                        ->orderByDesc('imported_at')
                        ->orderByDesc('id')
                        ->limit($limit)
                            ->get(['id', 'feed_source_id', 'slug', 'title', 'canonical_url', 'guid', 'published_at']);
            },
        );

        $items = $articles->map(function (Article $article): array {
            $isTickerOnly = $article->feedSource?->destination === 'breaking'
                || ($article->feed_source_id === null
                    && empty($article->canonical_url)
                    && str_starts_with((string) $article->guid, 'https://almanar.com.lb/'));

            return [
                'slug' => (string) $article->slug,
                'title' => (string) $article->title,
                'published_at' => $article->published_at?->toIso8601String(),
                'time' => $article->published_at?->format('H:i'),
                'linkable' => ! $isTickerOnly,
            ];
        })->values()->all();

        return response()->json([
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            'limit' => $limit,
            'items' => $items,
        ]);
    }
}
