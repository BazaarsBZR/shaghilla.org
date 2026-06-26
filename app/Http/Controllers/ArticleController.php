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
            ->firstOrFail();

        return view('pages.article', [
            'article' => $article,
        ]);
    }
}
