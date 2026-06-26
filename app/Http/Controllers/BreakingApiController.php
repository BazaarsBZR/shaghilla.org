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
            'api.breaking.v1.limit_'.$limit,
            now()->addSeconds(30),
            fn () => Article::query()
                ->where('status', 'published')
                ->where('is_breaking', true)
                ->orderByDesc('imported_at')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get(['slug', 'title', 'published_at']),
        );

        $items = $articles->map(function (Article $article): array {
            return [
                'slug' => (string) $article->slug,
                'title' => (string) $article->title,
                'published_at' => $article->published_at?->toIso8601String(),
                'time' => $article->published_at?->format('H:i'),
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
