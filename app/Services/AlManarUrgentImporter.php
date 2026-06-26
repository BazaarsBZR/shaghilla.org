<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AlManarUrgentImporter
{
    /**
     * Cached Schema::hasColumn('articles', ...) results.
     *
     * @var array<string, bool>
     */
    private static array $articlesColumnMemo = [];

    public function __construct(
        private readonly BreakingNewsLimiter $breakingNewsLimiter,
    ) {
    }

    private function hasArticlesColumn(string $column): bool
    {
        if (array_key_exists($column, self::$articlesColumnMemo)) {
            return self::$articlesColumnMemo[$column];
        }

        try {
            self::$articlesColumnMemo[$column] = Schema::hasColumn('articles', $column);
        } catch (\Throwable) {
            self::$articlesColumnMemo[$column] = false;
        }

        return self::$articlesColumnMemo[$column];
    }

    /**
     * @return array{imported:int, updated:int, skipped:int, cleared:int, failed:int, duration_ms:int}
     */
    public function importNow(): array
    {
        $start = microtime(true);

        if (! SiteSetting::getBool('almanar_urgent_enabled', false)) {
            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'cleared' => 0,
                'failed' => 0,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        }

        $limit = SiteSetting::getInt('almanar_urgent_limit', 10);
        $limit = max(1, min(25, $limit));

        $lock = Cache::lock('tasks.almanar_urgent_import', 120);
        if (! $lock->get()) {
            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'cleared' => 0,
                'failed' => 0,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        }

        try {
        $ajaxUrl = 'https://almanar.com.lb/ajaxify';

        try {
            $response = Http::timeout(20)
                ->withUserAgent('ShaghillaUrgentImporter/1.0 (+https://shaghilla.org)')
                ->asForm()
                ->retry(2, 500, function ($exception): bool {
                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        $status = $exception->response?->status();

                        return $status === 429 || ($status !== null && $status >= 500);
                    }

                    return true;
                }, false)
                ->post($ajaxUrl, [
                    'action' => 'manar_get_latest_news',
                    'urgent_only' => 'true',
                    'post_count' => (string) $limit,
                ]);
        } catch (\Throwable) {
            SiteSetting::setValues([
                'last_almanar_urgent_import_at' => now()->toIso8601String(),
                'last_almanar_urgent_import_result' => json_encode([
                    'imported' => 0,
                    'skipped' => 0,
                    'failed' => 1,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'cleared' => 0,
                'failed' => 1,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        }

        if (! $response->successful()) {
            SiteSetting::setValues([
                'last_almanar_urgent_import_at' => now()->toIso8601String(),
                'last_almanar_urgent_import_result' => json_encode([
                    'imported' => 0,
                    'skipped' => 0,
                    'failed' => 1,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'cleared' => 0,
                'failed' => 1,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        }

        $urgentItems = $this->parseUrgentListHtml((string) $response->body());
        $urgentItems = array_slice($urgentItems, 0, $limit);

        // Diagnostics: keep a small sample so the admin page can confirm scraping/parsing is working.
        try {
            SiteSetting::setValues([
                'last_almanar_urgent_fetch_at' => now()->toIso8601String(),
                'last_almanar_urgent_fetch_count' => (string) count($urgentItems),
                'last_almanar_urgent_fetch_sample' => json_encode(array_slice(array_map(
                    static fn (array $row): array => [
                        'title' => (string) ($row['title'] ?? ''),
                        'url' => (string) ($row['url'] ?? ''),
                        'published_at' => ($row['published_at'] ?? null) instanceof Carbon ? ($row['published_at'])->toIso8601String() : null,
                    ],
                    $urgentItems,
                ), 0, 5), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (\Throwable) {
            // ignore
        }

        if ($urgentItems === []) {
            SiteSetting::setValues([
                'last_almanar_urgent_import_at' => now()->toIso8601String(),
                'last_almanar_urgent_import_result' => json_encode([
                    'imported' => 0,
                    'updated' => 0,
                    'cleared' => 0,
                    'skipped' => 0,
                    'failed' => 0,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'cleared' => 0,
                'failed' => 0,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        }

        $defaultCategoryId = (int) Category::query()->where('slug', 'lebanon')->value('id');
        if (! $defaultCategoryId) {
            $defaultCategoryId = (int) Category::query()->orderBy('id')->value('id');
        }
        if (! $defaultCategoryId) {
            throw new \RuntimeException('Missing categories table seed (no categories found).');
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $keepHashes = [];

        foreach ($urgentItems as $item) {
            $sourceUrl = $item['url'];
            $title = $item['title'];
            $publishedAt = $item['published_at'];

            if ($sourceUrl === '' || $title === '') {
                $skipped++;
                continue;
            }

            $sourceHash = hash('sha256', $sourceUrl);
            $keepHashes[] = $sourceHash;

            $existing = Article::query()
                ->where('canonical_url_hash', $sourceHash)
                ->first();

            try {
                $articleHtml = $this->fetchArticleHtml($sourceUrl);
                $contentText = $articleHtml !== null ? $this->extractArticleContentFromHtml($articleHtml) : null;

                $contentText = $this->normalizeText($contentText);
                $contentForStorage = $contentText !== '' ? $contentText : $title;
                $excerpt = Str::limit($contentForStorage, 240);

                if ($existing) {
                    $updates = [
                        'title' => $title,
                        'excerpt' => $excerpt,
                        'content' => $contentForStorage,
                        'published_at' => $publishedAt ?: ($existing->published_at ?: now()),
                        'imported_at' => now(),
                        'is_breaking' => true,
                        'status' => 'published',
                    ];

                    if ($existing->guid !== $sourceUrl) {
                        $updates['guid'] = $sourceUrl;
                    }

                    $existing->forceFill($updates)->save();
                    $updated++;
                } else {
                    $payload = [
                        'feed_source_id' => null,
                        'category_id' => $defaultCategoryId,
                        'title' => $title,
                        'slug' => $this->makeUniqueSlug($title),
                        'excerpt' => $excerpt,
                        'content' => $contentForStorage,
                        // Keep canonical_url empty so we don't expose the source link on the public page / meta.
                        'canonical_url' => null,
                        // But keep a stable unique hash for de-duping.
                        'canonical_url_hash' => $sourceHash,
                        'guid' => $sourceUrl,
                        'guid_hash' => hash('sha256', 'almanar-urgent|'.$sourceUrl),
                        'image_url' => null,
                        'published_at' => $publishedAt ?: now(),
                        'imported_at' => now(),
                        'is_breaking' => true,
                        'language' => 'ar',
                        'status' => 'published',
                    ];

                    if ($this->hasArticlesColumn('show_on_home')) {
                        $payload['show_on_home'] = false;
                    }

                    if ($this->hasArticlesColumn('is_breaking_locked')) {
                        $payload['is_breaking_locked'] = true;
                    }

                    if ($this->hasArticlesColumn('show_on_home_locked')) {
                        $payload['show_on_home_locked'] = true;
                    }

                    Article::create($payload);

                    $imported++;
                }
            } catch (QueryException $exception) {
                $failed++;

                // If another concurrent run inserted the same URL, treat as skipped.
                if ($this->isDuplicateKeyException($exception)) {
                    $skipped++;
                    continue;
                }

                throw $exception;
            } catch (\Throwable) {
                $failed++;
                continue;
            }
        }

        // Replace breaking list: un-flag older Al-Manar urgent items not in the latest fetched set.
        $cleared = 0;
        if ($keepHashes !== []) {
            $query = Article::query()
                ->where('status', 'published')
                ->where('is_breaking', true)
                ->whereNull('feed_source_id')
                ->where('guid', 'like', 'https://almanar.com.lb/%')
                ->whereNotIn('canonical_url_hash', $keepHashes);

            // If the lock column exists, only touch items that were created by this importer.
            if ($this->hasArticlesColumn('is_breaking_locked')) {
                $query->where('is_breaking_locked', true);
            }

            $cleared = (int) $query->update(['is_breaking' => false]);
        }

        $this->breakingNewsLimiter->keepLatest();

        Cache::forget('news.breaking.ticker');
        Cache::forget('news.home.hero');
        Cache::forget('news.home.latest');

        SiteSetting::setValues([
            'last_almanar_urgent_import_at' => now()->toIso8601String(),
            'last_almanar_urgent_import_result' => json_encode([
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'cleared' => $cleared,
                'failed' => $failed,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'cleared' => $cleared,
            'failed' => $failed,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return array<int, array{title:string, url:string, published_at:?Carbon}>
     */
    private function parseUrgentListHtml(string $html): array
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);

        $nodes = $xpath->query(
            '//li[contains(concat(" ", normalize-space(@class), " "), " urgent-news ") and contains(concat(" ", normalize-space(@class), " "), " text-danger ")]',
        );

        if (! $nodes instanceof \DOMNodeList || $nodes->length === 0) {
            return [];
        }

        $items = [];

        /** @var \DOMElement $li */
        foreach ($nodes as $li) {
            $a = $xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " urgent-news ")]', $li)->item(0);
            if (! $a instanceof \DOMElement) {
                continue;
            }

            $href = trim((string) $a->getAttribute('href'));
            $title = trim($this->normalizeText($a->textContent));

            $publishedAt = null;
            $time = $xpath->query('.//time[contains(@class, "timeago")]', $li)->item(0);
            if ($time instanceof \DOMElement) {
                $datetime = trim((string) $time->getAttribute('datetime'));
                if ($datetime !== '') {
                    try {
                        $publishedAt = Carbon::parse($datetime)->setTimezone('Asia/Beirut');
                    } catch (\Throwable) {
                        $publishedAt = null;
                    }
                }
            }

            if ($href === '' || $title === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'url' => $href,
                'published_at' => $publishedAt,
            ];
        }

        return $items;
    }

    private function fetchArticleHtml(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $cacheKey = 'almanar.article_html.v1.'.hash('sha256', $url);

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($url): ?string {
            try {
                $response = Http::timeout(20)
                    ->withUserAgent('ShaghillaUrgentImporter/1.0 (+https://shaghilla.org)')
                    ->retry(2, 500, function ($exception): bool {
                        if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                            $status = $exception->response?->status();

                            return $status === 429 || ($status !== null && $status >= 500);
                        }

                        return true;
                    }, false)
                    ->get($url);

                if (! $response->successful()) {
                    return null;
                }

                $html = (string) $response->body();
                return $html !== '' ? $html : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    private function extractArticleContentFromHtml(string $html): ?string
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return null;
        }

        $xpath = new \DOMXPath($dom);

        $node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " mnr-article-content ")]')->item(0);
        if (! $node instanceof \DOMElement) {
            return null;
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        $text = $this->htmlToText($inner);
        return $text !== '' ? $text : null;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<script\\b[^>]*>.*?<\\/script>/is', '', $html) ?: $html;
        $html = preg_replace('/<style\\b[^>]*>.*?<\\/style>/is', '', $html) ?: $html;

        $html = preg_replace('/<\\s*br\\s*\\/?>/i', "\n", $html) ?: $html;
        $html = preg_replace('/<\\/p\\s*>/i', "\n\n", $html) ?: $html;
        $html = preg_replace('/<\\/div\\s*>/i', "\n", $html) ?: $html;

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?: $text;
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?: $text;

        return trim($text);
    }

    private function normalizeText(?string $text): string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\\s+/u', ' ', $text) ?: $text;

        return trim($text);
    }

    private function makeUniqueSlug(string $title): string
    {
        $base = $this->slugify($title);
        $slug = $base;
        $suffix = 2;

        while (Article::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;

            if ($suffix > 50) {
                $slug = $base.'-'.Str::lower(Str::random(10));
                break;
            }
        }

        return $slug;
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $sqlState = $errorInfo[0] ?? null;
        $driverCode = (int) ($errorInfo[1] ?? 0);

        return $sqlState === '23000' && in_array($driverCode, [1062, 19], true);
    }

    private function slugify(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s-]+/u', '', $text) ?: '';
        $text = preg_replace('/[\s-]+/u', '-', $text) ?: '';
        $text = trim($text, '-');
        $text = mb_substr($text, 0, 120, 'UTF-8');

        return $text !== '' ? $text : 'news-'.Str::lower(Str::random(10));
    }
}
