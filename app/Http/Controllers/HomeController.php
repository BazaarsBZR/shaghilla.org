<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\HostedVideo;
use App\Models\SiteSetting;
use App\Models\VideoItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    private const HOME_LATEST_TOTAL_LIMIT = 40;

    private const HOME_LATEST_CACHE_LIMIT = 48;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $latestPage = min(10, max(1, (int) $request->integer('latest_page', 1)));
        $viewData = Cache::flexible(
            "home.page.data.v2.{$latestPage}",
            [60, 300],
            function () use ($latestPage): array {
        $tickerLimit = (int) (SiteSetting::getValue('ticker_limit', '10') ?? 10);
        $tickerLimit = max(1, min(50, $tickerLimit));

        $newsLayout = SiteSetting::getValue('home_news_layout', 'mosaic');
        $newsTopSmallCount = SiteSetting::getInt('home_news_top_small_count', 4);
        $newsTopSmallCount = max(0, min(12, $newsTopSmallCount));

        $newsLatestLimit = SiteSetting::getInt('home_news_latest_limit', 9);
        $newsLatestLimit = max(6, min(9, $newsLatestLimit));

        $breakingItems = Cache::remember(
            'news.breaking.ticker.v2',
            now()->addMinutes(5),
            function () use ($tickerLimit) {
                $items = Article::query()
                    ->with(['feedSource'])
                    ->where('status', 'published')
                    ->where('is_breaking', true)
                    ->orderByDesc('imported_at')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit($tickerLimit)
                    ->get();

                return $items->isNotEmpty()
                    ? $items
                    : Article::query()
                        ->with(['feedSource'])
                        ->where('status', 'published')
                        ->orderByDesc('published_at')
                        ->orderByDesc('imported_at')
                        ->orderByDesc('id')
                        ->limit($tickerLimit)
                        ->get();
            },
        );

        $newsData = $this->buildHomeNewsData(
            layout: $newsLayout,
            topSmallCount: $newsTopSmallCount,
            perPage: $newsLatestLimit,
            page: $latestPage,
        );

        $homeVideosEnabled = SiteSetting::getBool('home_hosted_videos_enabled', true);
        $homeVideosTitle = SiteSetting::getValue('home_hosted_videos_title_ar', 'الفيديو');
        $homeVideosLimit = max(1, min(12, SiteSetting::getInt('home_hosted_videos_limit', 12)));

        $homeVideosLayout = SiteSetting::getValue('home_hosted_videos_layout', 'grid');
        $homeVideosLayout = in_array($homeVideosLayout, ['grid', 'carousel'], true) ? $homeVideosLayout : 'grid';

        $homeVideosIncludeYoutube = SiteSetting::getBool('home_hosted_videos_include_youtube', true);
        $homeVideosPlaceholderEnabled = SiteSetting::getBool('home_hosted_videos_placeholders_enabled', true);
        $homeVideosPlaceholderCount = max(0, min(12, SiteSetting::getInt('home_hosted_videos_placeholder_count', 8)));
        $homeVideosPlaceholderTitle = SiteSetting::getValue('home_hosted_videos_placeholder_title_ar', 'رابطة الشغيلة');
        $homeVideosPlaceholderYoutubeUrl = SiteSetting::getValue(
            'home_hosted_videos_placeholder_youtube_url',
            'https://www.youtube.com/watch?v=QyR01ZMIIqE&t=10690s',
        );

        $homeVideos = collect();

        if ($homeVideosEnabled) {
            try {
                $homeVideos = Cache::remember(
                    'home.hosted_videos',
                    now()->addMinutes(10),
                    fn () => HostedVideo::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderByDesc('published_at')
                        ->limit(30)
                        ->get(),
                );
            } catch (\Throwable) {
                $homeVideos = collect();
            }

            if ($homeVideosIncludeYoutube) {
                try {
                    $youtubeItems = Cache::remember(
                        'home.videos',
                        now()->addMinutes(10),
                        fn () => VideoItem::query()
                            ->where('is_active', true)
                            ->where('type', VideoItem::TYPE_HOME)
                            ->orderBy('sort_order')
                            ->orderByDesc('published_at')
                            ->limit(30)
                            ->get(),
                    );
                } catch (\Throwable) {
                    $youtubeItems = collect();
                }

                $homeVideos = $homeVideos->concat($youtubeItems);
            }

            if ($homeVideos->isEmpty() && $homeVideosPlaceholderEnabled && $homeVideosPlaceholderCount > 0) {
                $placeholders = collect();
                for ($i = 0; $i < $homeVideosPlaceholderCount; $i++) {
                    $placeholders->push(new VideoItem([
                        'type' => VideoItem::TYPE_HOME,
                        'title' => $homeVideosPlaceholderTitle,
                        'youtube_url' => $homeVideosPlaceholderYoutubeUrl,
                        'thumbnail_url' => null,
                        'published_at' => null,
                        'sort_order' => 0,
                        'is_active' => true,
                    ]));
                }
                $homeVideos = $placeholders;
            }

            $homeVideos = $homeVideos->take($homeVideosLimit);
        }

        $membership = [
            'enabled' => SiteSetting::getBool('home_membership_enabled', true),
            'style' => SiteSetting::getValue('home_membership_style', 'dark'),
            'title' => SiteSetting::getValue('home_membership_title_ar', 'انتساب'),
            'body' => SiteSetting::getValue(
                'home_membership_body_ar',
                SiteSetting::getValue('membership_page_intro_ar', __('ui.membership.intro_default')),
            ),
            'button_label' => SiteSetting::getValue('home_membership_button_label_ar', __('ui.nav.membership')),
            'button_mode' => SiteSetting::getValue('home_membership_button_mode', 'membership'),
            'button_url' => SiteSetting::getValue('home_membership_button_url', ''),
            'image_url' => SiteSetting::getValue('home_membership_image_url', ''),
        ];

        $membershipButtonHref = route('membership');
        if (($membership['button_mode'] ?? '') === 'custom') {
            $customUrl = trim((string) ($membership['button_url'] ?? ''));
            if ($customUrl !== '' && filter_var($customUrl, FILTER_VALIDATE_URL)) {
                $membershipButtonHref = $customUrl;
            }
        }
        $membership['button_href'] = $membershipButtonHref;

        $contact = [
            'enabled' => SiteSetting::getBool('home_contact_enabled', true),
            'style' => SiteSetting::getValue('home_contact_style', 'light'),
            'title' => SiteSetting::getValue('home_contact_title_ar', 'طلب الخدمة'),
            'body' => SiteSetting::getValue(
                'home_contact_body_ar',
                SiteSetting::getValue('contact_page_intro_ar', __('ui.contact.intro_default')),
            ),
            'button_label' => SiteSetting::getValue('home_contact_button_label_ar', __('ui.nav.contact')),
            'button_mode' => SiteSetting::getValue('home_contact_button_mode', 'contact'),
            'button_url' => SiteSetting::getValue('home_contact_button_url', ''),
            'image_url' => SiteSetting::getValue('home_contact_image_url', ''),
        ];

        $contactButtonHref = route('contact');
        if (($contact['button_mode'] ?? '') === 'custom') {
            $customUrl = trim((string) ($contact['button_url'] ?? ''));
            if ($customUrl !== '' && filter_var($customUrl, FILTER_VALIDATE_URL)) {
                $contactButtonHref = $customUrl;
            }
        }
        $contact['button_href'] = $contactButtonHref;

                return [
            'breakingItems' => $breakingItems,
            'hero' => $newsData['heroArticle'],
            'latestArticles' => $newsData['latestPaginator']->getCollection(),
            'latestPaginator' => $newsData['latestPaginator'],
            'newsLayout' => $newsData['newsLayout'],
            'newsTopSmallCount' => $newsData['newsTopSmallCount'],
            'topGridArticles' => $newsData['topGridArticles'],
            'homeVideosEnabled' => $homeVideosEnabled,
            'homeVideos' => $homeVideos,
            'homeVideosTitle' => $homeVideosTitle,
            'homeVideosLayout' => $homeVideosLayout,
            'membershipSection' => $membership,
            'contactSection' => $contact,
                ];
            },
        );

        return view('pages.home', $viewData);
    }

    public function latest(Request $request): JsonResponse
    {
        $newsLayout = SiteSetting::getValue('home_news_layout', 'mosaic');
        $newsTopSmallCount = max(0, min(12, SiteSetting::getInt('home_news_top_small_count', 4)));
        $newsLatestLimit = max(6, min(9, SiteSetting::getInt('home_news_latest_limit', 9)));
        $latestPage = max(1, (int) $request->integer('latest_page', 1));

        $newsData = $this->buildHomeNewsData(
            layout: $newsLayout,
            topSmallCount: $newsTopSmallCount,
            perPage: $newsLatestLimit,
            page: $latestPage,
        );

        return response()->json([
            'html' => view('partials.home.latest-news-results', [
                'latestArticles' => $newsData['latestPaginator']->getCollection(),
                'latestPaginator' => $newsData['latestPaginator'],
            ])->render(),
            'page' => $newsData['latestPaginator']->currentPage(),
            'last_page' => $newsData['latestPaginator']->lastPage(),
            'total' => $newsData['latestPaginator']->total(),
            'per_page' => $newsData['latestPaginator']->perPage(),
        ]);
    }

    private function buildHomeNewsData(
        string $layout,
        int $topSmallCount,
        int $perPage,
        int $page,
    ): array {
        $heroArticle = Cache::remember(
            'news.home.hero',
            now()->addMinutes(5),
            fn () => $this->baseHomeArticlesQuery()->first(),
        );

        if ($layout === 'grid') {
            $heroArticle = null;
        }

        $latestArticlesAll = Cache::remember(
            'news.home.latest',
            now()->addMinutes(5),
            fn () => $this->baseHomeArticlesQuery()
                ->limit(self::HOME_LATEST_CACHE_LIMIT)
                ->get(),
        );

        $withoutHero = $latestArticlesAll
            ->reject(fn ($article) => $heroArticle && (int) $article->id === (int) $heroArticle->id)
            ->values();

        $topGridArticles = $layout === 'mosaic'
            ? $withoutHero->take($topSmallCount)->values()
            : collect();

        $featuredIds = collect()
            ->when($heroArticle, fn ($collection) => $collection->push((int) $heroArticle->id))
            ->merge($topGridArticles->pluck('id')->map(fn ($id) => (int) $id))
            ->unique()
            ->values();

        $latestPool = $latestArticlesAll
            ->reject(fn ($article) => $featuredIds->contains((int) $article->id))
            ->take(self::HOME_LATEST_TOTAL_LIMIT)
            ->values();

        $latestPaginator = $this->paginateLatestCollection(
            items: $latestPool,
            perPage: $perPage,
            page: $page,
        );

        return [
            'newsLayout' => $layout,
            'newsTopSmallCount' => $topSmallCount,
            'heroArticle' => $heroArticle,
            'topGridArticles' => $topGridArticles,
            'latestPaginator' => $latestPaginator,
        ];
    }

    private function baseHomeArticlesQuery()
    {
        $query = Article::query()
            ->with(['feedSource'])
            ->where('status', 'published');

        if (Article::hasShowOnHomeColumn()) {
            $query->where('show_on_home', true);
        }

        $query
            ->where(function ($articles): void {
                $articles
                    ->whereRaw("LENGTH(TRIM(COALESCE(content, ''))) >= 80")
                    ->orWhereRaw("LENGTH(TRIM(COALESCE(excerpt, ''))) >= 45");
            })
            ->whereDoesntHave('feedSource', fn ($feed) => $feed->where('destination', 'breaking'))
            ->where(function ($articles) {
                $articles
                    ->whereNotNull('canonical_url')
                    ->orWhereNull('guid')
                    ->orWhere('guid', 'not like', 'https://almanar.com.lb/%');
            });

        return $query
            ->orderByDesc('imported_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    private function paginateLatestCollection($items, int $perPage, int $page): LengthAwarePaginator
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $total = $items->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($page, $lastPage);

        return new LengthAwarePaginator(
            $items->forPage($currentPage, $perPage)->values(),
            $total,
            $perPage,
            $currentPage,
            [
                'path' => route('home'),
                'pageName' => 'latest_page',
            ],
        );
    }
}
