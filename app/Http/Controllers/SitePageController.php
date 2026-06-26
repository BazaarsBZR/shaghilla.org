<?php

namespace App\Http\Controllers;

use App\Models\SitePage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SitePageController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $page = SitePage::query()
            ->where('type', SitePage::TYPE_PAGE)
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $title = $page->displayTitle();

        return view('pages.page', [
            'page' => $page,
            'title' => $title,
        ]);
    }
}

