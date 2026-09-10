<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function show(string $slug): View
    {
        $article = Article::query()
            ->with(['feedSource', 'category'])
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        $isBreakingFeedHeadline = $article->feedSource?->destination === 'breaking';
        $isAlManarUrgentHeadline = $article->feed_source_id === null
            && empty($article->canonical_url)
            && str_starts_with((string) $article->guid, 'https://almanar.com.lb/');

        abort_if($isBreakingFeedHeadline || $isAlManarUrgentHeadline, 404);

        return view('pages.article', [
            'article' => $article,
        ]);
    }
}
