<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));
        $query = mb_substr($query, 0, 120, 'UTF-8');

        $articles = Article::query()
            ->with(['feedSource'])
            ->where('status', 'published')
            ->when(
                $query !== '',
                fn ($builder) => $builder->where(function ($builder) use ($query): void {
                    $builder
                        ->where('title', 'like', "%{$query}%")
                        ->orWhere('excerpt', 'like', "%{$query}%")
                        ->orWhere('content', 'like', "%{$query}%");
                }),
            )
            ->orderByDesc('published_at')
            ->paginate(18)
            ->withQueryString();

        return view('pages.search', [
            'query' => $query,
            'articles' => $articles,
        ]);
    }
}

