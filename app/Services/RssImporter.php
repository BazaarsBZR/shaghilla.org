<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\FeedSource;
use App\Models\KeywordRule;
use App\Models\SiteSetting;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleXMLElement;

class RssImporter
{
    public const EXISTING_ITEM_BEHAVIOR_FILL_MISSING = 'fill_missing';
    public const EXISTING_ITEM_BEHAVIOR_OVERWRITE_IF_TITLE_MATCH = 'overwrite_if_title_match';
    public const EXISTING_ITEM_BEHAVIOR_OVERWRITE = 'overwrite';
    public const EXISTING_ITEM_BEHAVIOR_SKIP = 'skip';

    /**
     * Cached Schema::hasColumn('articles', ...) results.
     *
     * @var array<string, bool>
     */
    private static array $articlesColumnMemo = [];

    public function __construct(
        private readonly ArticleScraper $articleScraper,
        private readonly BreakingNewsLimiter $breakingNewsLimiter,
    ) {
    }

    /**
     * @return array{imported:int, updated:int, skipped:int, filtered_no_image:int, failed_feeds:int, duration_ms:int}
     */
    public function importAll(
        ?int $itemLimitOverride = null,
        ?string $existingItemBehaviorOverride = null,
        ?bool $forceNoSkipOverride = null,
        ?bool $requireImageForHomeOverride = null,
        ?int $feedOffsetOverride = null,
        ?int $itemOffsetOverride = null,
    ): array
    {
        $start = microtime(true);

        $activeFeedsQuery = FeedSource::query()
            ->where('is_active', true)
            ->orderBy('id');

        if ($feedOffsetOverride !== null) {
            $activeFeedsQuery
                ->offset(max(0, $feedOffsetOverride))
                ->limit(1);
        }

        $activeFeeds = $activeFeedsQuery->get();

        if ($activeFeeds->isEmpty()) {
            $result = [
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'filtered_no_image' => 0,
                'failed_feeds' => 0,
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
            ];

            SiteSetting::setValues([
                'last_rss_import_at' => now()->toIso8601String(),
                'last_rss_import_result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return $result;
        }

        $defaultCategoryId = (int) Category::query()->where('slug', 'lebanon')->value('id');
        $workerCategoryId = (int) Category::query()->where('slug', 'workers')->value('id');

        if (! $defaultCategoryId || ! $workerCategoryId) {
            throw new \RuntimeException('Missing required categories (lebanon/workers).');
        }

        $keywordRules = KeywordRule::query()
            ->where('is_active', true)
            ->get(['type', 'keyword'])
            ->groupBy('type')
            ->map(fn ($rules) => $rules->pluck('keyword')->filter()->values()->all())
            ->all();

        $workerKeywords = $keywordRules[KeywordRule::TYPE_WORKER] ?? [];
        $breakingKeywords = $keywordRules[KeywordRule::TYPE_BREAKING] ?? [];

        $itemLimit = $itemLimitOverride ?? (int) (SiteSetting::getValue('rss_item_limit', '10') ?? 10);
        $itemLimit = max(1, min(50, $itemLimit));
        $itemOffset = max(0, min(100, $itemOffsetOverride ?? 0));

        $forceNoSkip = $forceNoSkipOverride ?? SiteSetting::getBool('rss_force_no_skip', false);
        $requireImageForHome = $requireImageForHomeOverride ?? SiteSetting::getBool('rss_require_image_for_home', false);

        $existingItemBehavior = $this->normalizeExistingItemBehavior(
            $existingItemBehaviorOverride
                ?? SiteSetting::getValue('rss_existing_item_behavior', self::EXISTING_ITEM_BEHAVIOR_FILL_MISSING)
                ?? self::EXISTING_ITEM_BEHAVIOR_FILL_MISSING,
        );

        if ($forceNoSkip) {
            $existingItemBehavior = self::EXISTING_ITEM_BEHAVIOR_OVERWRITE;
        }

        $importedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $updatedCount = 0;
        $filteredNoImageCount = 0;

        foreach ($activeFeeds as $feedSource) {
            try {
                $result = $this->importFeed(
                    $feedSource,
                    $defaultCategoryId,
                    $workerCategoryId,
                    $workerKeywords,
                    $breakingKeywords,
                    $itemLimit,
                    $itemOffset,
                    $existingItemBehavior,
                    $forceNoSkip,
                    $requireImageForHome,
                );

                $importedCount += $result['imported'];
                $skippedCount += $result['skipped'];
                $updatedCount += $result['updated'] ?? 0;
                $filteredNoImageCount += $result['filtered_no_image'] ?? 0;
            } catch (\Throwable $exception) {
                $failedCount++;

                Log::error('RSS import failed for feed source', [
                    'feed_source_id' => $feedSource->id,
                    'url' => $feedSource->url,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        $this->breakingNewsLimiter->keepLatest();

        Cache::forget('news.breaking.ticker');
        Cache::forget('news.breaking.live');
        Cache::forget('news.home.hero');
        Cache::forget('news.home.latest');

        SiteSetting::setValues([
            'last_rss_import_at' => now()->toIso8601String(),
        ]);

        $result = [
            'imported' => $importedCount,
            'skipped' => $skippedCount,
            'updated' => $updatedCount,
            'filtered_no_image' => $filteredNoImageCount,
            'failed_feeds' => $failedCount,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ];

        SiteSetting::setValues([
            'last_rss_import_result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return $result;
    }

    private function normalizeExistingItemBehavior(string $behavior): string
    {
        $behavior = strtolower(trim($behavior));

        return in_array($behavior, [
            self::EXISTING_ITEM_BEHAVIOR_FILL_MISSING,
            self::EXISTING_ITEM_BEHAVIOR_OVERWRITE_IF_TITLE_MATCH,
            self::EXISTING_ITEM_BEHAVIOR_OVERWRITE,
            self::EXISTING_ITEM_BEHAVIOR_SKIP,
        ], true) ? $behavior : self::EXISTING_ITEM_BEHAVIOR_FILL_MISSING;
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

    private function containsArabic(string $text): bool
    {
        return preg_match('/\p{Arabic}/u', $text) === 1;
    }

    /**
     * @param  array<int, string>  $workerKeywords
     * @param  array<int, string>  $breakingKeywords
     * @return array{imported:int, updated:int, skipped:int, filtered_no_image:int}
     */
    private function importFeed(
        FeedSource $feedSource,
        int $defaultCategoryId,
        int $workerCategoryId,
        array $workerKeywords,
        array $breakingKeywords,
        int $itemLimit,
        int $itemOffset,
        string $existingItemBehavior,
        bool $forceNoSkip,
        bool $requireImageForHome,
    ): array {
        $headers = [];
        if (! $forceNoSkip) {
            if ($feedSource->etag) {
                $headers['If-None-Match'] = $feedSource->etag;
            }
            if ($feedSource->last_modified) {
                $headers['If-Modified-Since'] = $feedSource->last_modified;
            }
        }

        $url = $this->encodeUnicodeUrl($feedSource->url);

        $response = Http::timeout(20)
            ->withHeaders($headers)
            ->withUserAgent('ShaghillaRSSImporter/1.0 (+https://shaghilla.org)')
            ->retry(2, 500, function ($exception): bool {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $status = $exception->response?->status();

                    return $status === 429 || ($status !== null && $status >= 500);
                }

                return true;
            }, false)
            ->get($url);

        if ($response->status() === 304) {
            $feedSource->forceFill(['last_fetched_at' => now()])->save();

            return ['imported' => 0, 'skipped' => 0, 'updated' => 0, 'filtered_no_image' => 0];
        }

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        $xml = $this->parseRssXml($response->body());
        $items = $this->extractRssItems($xml);

        // Always process the newest items first. Some feeds are not strictly ordered.
        $itemsWithTimestamps = array_map(function (SimpleXMLElement $item): array {
            $publishedAt = $this->parsePublishedAt($item);

            return [
                'item' => $item,
                'ts' => $publishedAt?->getTimestamp() ?? 0,
            ];
        }, $items);

        usort($itemsWithTimestamps, static fn (array $a, array $b): int => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));

        $items = array_slice(
            array_map(static fn (array $row): SimpleXMLElement => $row['item'], $itemsWithTimestamps),
            $itemOffset,
            $itemLimit,
        );

        $destination = (string) $feedSource->destination;

        $imported = 0;
        $skipped = 0;
        $updated = 0;
        $filteredNoImage = 0;

        // For "Breaking only" feeds, we treat imported items as the authoritative breaking list.
        // We'll later un-flag older breaking items from this feed that are not in the latest batch.
        $keepGuidHashes = [];

        foreach ($items as $item) {
            $data = $this->parseRssItem($feedSource, $item);

            if (! $this->containsArabic($data['title'])) {
                $skipped++;
                continue;
            }

            if ($this->shouldSkipItem($feedSource, $data, $forceNoSkip)) {
                $skipped++;
                continue;
            }

            $keepGuidHashes[] = $data['guid_hash'];

            $existing = null;
            if ($data['canonical_url_hash']) {
                $existing = Article::query()->where('canonical_url_hash', $data['canonical_url_hash'])->first();
            }
            if (! $existing) {
                $existing = Article::query()
                    ->where('feed_source_id', $feedSource->id)
                    ->where('guid_hash', $data['guid_hash'])
                    ->first();
            }

            if ($existing) {
                if (! app()->environment('testing')) {
                    $data = $this->enrichFromWebPageIfNeeded($data, $existing);
                    $data = $this->downloadImageIfPossible($data, $existing);
                }

                $combinedText = $this->normalizeText($data['title'].' '.$data['excerpt'].' '.$data['content']);
                $isBreaking = $this->matchesAnyKeyword($combinedText, $breakingKeywords);
                $isWorker = $this->matchesAnyKeyword($combinedText, $workerKeywords);

                $desiredShowOnHome = $destination !== FeedSource::DESTINATION_BREAKING;
                $desiredBreaking = match ($destination) {
                    FeedSource::DESTINATION_BREAKING => true,
                    FeedSource::DESTINATION_HOME => false,
                    default => $isBreaking,
                };

                $breakingLocked = $this->hasArticlesColumn('is_breaking_locked')
                    ? (bool) ($existing->is_breaking_locked ?? false)
                    : false;

                $showOnHomeLocked = $this->hasArticlesColumn('show_on_home_locked')
                    ? (bool) ($existing->show_on_home_locked ?? false)
                    : false;

                $updates = [];

                $titleMatches = trim((string) ($existing->title ?? '')) === $data['title'];
                $shouldOverwrite = $existingItemBehavior === self::EXISTING_ITEM_BEHAVIOR_OVERWRITE
                    || ($existingItemBehavior === self::EXISTING_ITEM_BEHAVIOR_OVERWRITE_IF_TITLE_MATCH && $titleMatches);

                if ($existingItemBehavior === self::EXISTING_ITEM_BEHAVIOR_SKIP) {
                    $skipped++;
                    continue;
                }

                if (! $existing->canonical_url && $data['canonical_url']) {
                    $updates['canonical_url'] = $data['canonical_url'];
                }
                if (! $existing->canonical_url_hash && $data['canonical_url_hash']) {
                    $updates['canonical_url_hash'] = $data['canonical_url_hash'];
                }
                if (! $existing->guid && $data['guid']) {
                    $updates['guid'] = $data['guid'];
                }
                if ($shouldOverwrite) {
                    if (
                        $existingItemBehavior === self::EXISTING_ITEM_BEHAVIOR_OVERWRITE
                        && $data['title'] !== ''
                        && $data['title'] !== (string) ($existing->title ?? '')
                    ) {
                        $updates['title'] = $data['title'];
                    }

                    $incomingExcerpt = trim((string) ($data['excerpt'] ?? ''));
                    if ($incomingExcerpt !== '' && $incomingExcerpt !== (string) ($existing->excerpt ?? '')) {
                        $updates['excerpt'] = $data['excerpt'];
                    }

                    $incomingContent = trim((string) ($data['content'] ?? ''));
                    if ($incomingContent !== '' && $incomingContent !== (string) ($existing->content ?? '')) {
                        $updates['content'] = $data['content'];
                    }

                    $updates['imported_at'] = now();
                } else {
                    if (! $existing->excerpt && $data['excerpt']) {
                        $updates['excerpt'] = $data['excerpt'];
                    }
                    if (! $existing->content && $data['content']) {
                        $updates['content'] = $data['content'];
                    }
                }
                $incomingImageUrl = trim((string) ($data['image_url'] ?? ''));
                $existingImageUrl = trim((string) ($existing->image_url ?? ''));

                if ($incomingImageUrl !== '' && ! $this->isPlaceholderImageUrl($incomingImageUrl)) {
                    if (
                        $existingImageUrl === ''
                        || $this->isPlaceholderImageUrl($existingImageUrl)
                        || ($this->isLocalUploadUrl($incomingImageUrl) && ! $this->isLocalUploadUrl($existingImageUrl))
                    ) {
                        $updates['image_url'] = $incomingImageUrl;
                    }
                } elseif ($existingImageUrl !== '' && $this->isPlaceholderImageUrl($existingImageUrl)) {
                    // Drop placeholder images (e.g., Lebanon24 purple templates) to show our neutral fallback instead.
                    $updates['image_url'] = null;
                }
                if ($data['published_at']) {
                    if (! $existing->published_at) {
                        $updates['published_at'] = $data['published_at'];
                    } elseif ($shouldOverwrite && $data['published_at']?->notEqualTo($existing->published_at)) {
                        $updates['published_at'] = $data['published_at'];
                    }
                }
                if ($isWorker && $existing->category_id !== $workerCategoryId) {
                    $updates['category_id'] = $workerCategoryId;
                }

                // Destination-managed "Breaking only" feeds must be authoritative:
                // - Always keep these items flagged as breaking (even if locked).
                // - Always refresh imported_at so the ticker/order reflects "latest fetched".
                // - Keep them off the Home page when the show_on_home column exists.
                if ($destination === FeedSource::DESTINATION_BREAKING) {
                    $updates['is_breaking'] = true;
                    $updates['imported_at'] = now();

                    if ($this->hasArticlesColumn('show_on_home')) {
                        $updates['show_on_home'] = false;
                    }

                    if ($this->hasArticlesColumn('is_breaking_locked')) {
                        $updates['is_breaking_locked'] = true;
                    }

                    if ($this->hasArticlesColumn('show_on_home_locked')) {
                        $updates['show_on_home_locked'] = true;
                    }
                }

                if (! $breakingLocked) {
                    if ($destination === FeedSource::DESTINATION_HOME) {
                        if ($existing->is_breaking) {
                            $updates['is_breaking'] = false;
                        }
                    } elseif ($destination === FeedSource::DESTINATION_BREAKING) {
                        if (! $existing->is_breaking) {
                            $updates['is_breaking'] = true;
                        }
                    } elseif ($desiredBreaking && ! $existing->is_breaking) {
                        $updates['is_breaking'] = true;
                    }
                }

                if ($this->hasArticlesColumn('show_on_home') && ! $showOnHomeLocked) {
                    $currentShowOnHome = (bool) ($existing->show_on_home ?? true);
                    if ($currentShowOnHome !== $desiredShowOnHome) {
                        $updates['show_on_home'] = $desiredShowOnHome;
                    }
                }

                // Lock destination-managed feeds so later keyword runs don't override placement.
                if ($destination === FeedSource::DESTINATION_BREAKING) {
                    if ($this->hasArticlesColumn('is_breaking_locked')) {
                        $updates['is_breaking_locked'] = true;
                    }
                    if ($this->hasArticlesColumn('show_on_home_locked')) {
                        $updates['show_on_home_locked'] = true;
                    }
                }

                if (
                    $requireImageForHome
                    && $desiredShowOnHome
                    && $this->hasArticlesColumn('show_on_home')
                    && ! $showOnHomeLocked
                ) {
                    $finalImageUrl = array_key_exists('image_url', $updates)
                        ? trim((string) ($updates['image_url'] ?? ''))
                        : $existingImageUrl;

                    if ($finalImageUrl === '' || $this->isPlaceholderImageUrl($finalImageUrl)) {
                        $updates['show_on_home'] = false;
                    }
                }

                if (! empty($updates)) {
                    $existing->forceFill($updates)->save();
                    $updated++;
                }

                continue;
            }

            if (! app()->environment('testing')) {
                $data = $this->enrichFromWebPageIfNeeded($data, null);
                $data = $this->downloadImageIfPossible($data, null);
            }

            $combinedText = $this->normalizeText($data['title'].' '.$data['excerpt'].' '.$data['content']);
            $isBreaking = $this->matchesAnyKeyword($combinedText, $breakingKeywords);
            $isWorker = $this->matchesAnyKeyword($combinedText, $workerKeywords);

            $showOnHome = $destination !== FeedSource::DESTINATION_BREAKING;

            if ($requireImageForHome && $showOnHome) {
                $imageUrl = trim((string) ($data['image_url'] ?? ''));
                if ($imageUrl === '' || $this->isPlaceholderImageUrl($imageUrl)) {
                    $filteredNoImage++;
                    continue;
                }
            }

            $desiredBreaking = match ($destination) {
                FeedSource::DESTINATION_BREAKING => true,
                FeedSource::DESTINATION_HOME => false,
                default => $isBreaking,
            };

            $categoryId = $isWorker
                ? $workerCategoryId
                : ($feedSource->default_category_id ?: $defaultCategoryId);

            $language = $this->detectLanguage($data['title'].' '.$data['excerpt'].' '.$data['content']);

            try {
                $payload = [
                    'feed_source_id' => $feedSource->id,
                    'category_id' => $categoryId,
                    'title' => $data['title'],
                    'slug' => $this->makeUniqueSlug($data['title']),
                    'excerpt' => $data['excerpt'],
                    'content' => $data['content'],
                    'canonical_url' => $data['canonical_url'],
                    'canonical_url_hash' => $data['canonical_url_hash'],
                    'guid' => $data['guid'],
                    'guid_hash' => $data['guid_hash'],
                    'image_url' => $data['image_url'],
                    'published_at' => $data['published_at'],
                    'imported_at' => now(),
                    'is_breaking' => $desiredBreaking,
                    'language' => $language,
                    'status' => 'published',
                ];

                if ($this->hasArticlesColumn('show_on_home')) {
                    $payload['show_on_home'] = $showOnHome;
                }

                if ($this->hasArticlesColumn('is_breaking_locked')) {
                    $payload['is_breaking_locked'] = $destination === FeedSource::DESTINATION_BREAKING;
                }

                if ($this->hasArticlesColumn('show_on_home_locked')) {
                    $payload['show_on_home_locked'] = $destination === FeedSource::DESTINATION_BREAKING;
                }

                Article::create($payload);

                $imported++;
            } catch (QueryException $exception) {
                if ($this->isDuplicateKeyException($exception)) {
                    if ($existingItemBehavior === self::EXISTING_ITEM_BEHAVIOR_SKIP) {
                        $skipped++;
                        continue;
                    }

                    $existing = null;
                    if ($data['canonical_url_hash']) {
                        $existing = Article::query()->where('canonical_url_hash', $data['canonical_url_hash'])->first();
                    }
                    if (! $existing) {
                        $existing = Article::query()
                            ->where('feed_source_id', $feedSource->id)
                            ->where('guid_hash', $data['guid_hash'])
                            ->first();
                    }

                    if ($existing) {
                        $existing->forceFill(['imported_at' => now()])->save();
                        $updated++;
                        continue;
                    }

                    $skipped++;
                    continue;
                }

                throw $exception;
            }
        }

        $feedSource->forceFill([
            'last_fetched_at' => now(),
            'etag' => $response->header('ETag') ?: $feedSource->etag,
            'last_modified' => $response->header('Last-Modified') ?: $feedSource->last_modified,
        ])->save();

        // Replace breaking list for "Breaking only" RSS feeds:
        // un-flag older breaking items from this feed that are not in the latest fetched set.
        if ($destination === FeedSource::DESTINATION_BREAKING && $keepGuidHashes !== []) {
            $query = Article::query()
                ->where('feed_source_id', $feedSource->id)
                ->where('status', 'published')
                ->where('is_breaking', true)
                ->whereNotIn('guid_hash', $keepGuidHashes);

            $query->update(['is_breaking' => false]);
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'updated' => $updated, 'filtered_no_image' => $filteredNoImage];
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo ?? [];
        $sqlState = $errorInfo[0] ?? null;
        $driverCode = (int) ($errorInfo[1] ?? 0);

        return $sqlState === '23000' && $driverCode === 1062;
    }

    private function parseRssXml(string $xml): SimpleXMLElement
    {
        libxml_use_internal_errors(true);

        $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);
        if ($parsed === false) {
            $errors = array_map(
                static fn (\LibXMLError $error) => trim($error->message),
                libxml_get_errors(),
            );

            libxml_clear_errors();

            throw new \RuntimeException('Invalid RSS XML: '.implode(' | ', $errors));
        }

        return $parsed;
    }

    /**
     * @return array<int, SimpleXMLElement>
     */
    private function extractRssItems(SimpleXMLElement $xml): array
    {
        if (isset($xml->channel->item)) {
            return iterator_to_array($xml->channel->item, false);
        }

        if (isset($xml->entry)) {
            return iterator_to_array($xml->entry, false);
        }

        return [];
    }

    /**
     * @return array{
     *     title:string,
     *     canonical_url:?string,
     *     canonical_url_hash:?string,
     *     guid:?string,
     *     guid_hash:string,
     *     excerpt:?string,
     *     content:?string,
     *     image_url:?string,
     *     published_at:\Carbon\CarbonInterface,
     * }
     */
    private function parseRssItem(FeedSource $feedSource, SimpleXMLElement $item): array
    {
        $title = trim((string) ($item->title ?? ''));
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim(preg_replace('/\s+/u', ' ', strip_tags($title)) ?: '');

        $guid = isset($item->guid) ? trim((string) $item->guid) : null;
        $link = isset($item->link) ? trim((string) $item->link) : null;

        $canonicalUrl = $link ?: (filter_var($guid, FILTER_VALIDATE_URL) ? $guid : null);
        $canonicalUrlHash = $canonicalUrl ? hash('sha256', $canonicalUrl) : null;

        $guidBasis = $guid ?: $canonicalUrl ?: $title;
        $guidHash = hash('sha256', $feedSource->id.'|'.$guidBasis);

        $publishedAt = $this->parsePublishedAt($item) ?? now();

        $description = isset($item->description) ? (string) $item->description : '';
        $contentEncoded = $this->getNamespacedValue($item, 'http://purl.org/rss/1.0/modules/content/', 'encoded');

        $contentHtml = trim($contentEncoded ?: $description);
        $excerpt = $this->makeExcerpt($description ?: $contentHtml);

        $imageUrl =
            $this->extractEnclosureImageUrl($item)
            ?: $this->extractMediaImageUrl($item)
            ?: $this->extractFirstImageFromHtml($contentHtml)
            ?: $this->extractFirstImageFromHtml($description);
        $imageUrl = $this->normalizeMaybeRelativeUrl($imageUrl, $canonicalUrl);

        return [
            'title' => $title,
            'canonical_url' => $canonicalUrl,
            'canonical_url_hash' => $canonicalUrlHash,
            'guid' => $guid,
            'guid_hash' => $guidHash,
            'excerpt' => $excerpt,
            'content' => $contentHtml ?: null,
            'image_url' => $imageUrl,
            'published_at' => $publishedAt,
        ];
    }

    /**
     * @param  array{
     *     title:string,
     *     canonical_url:?string,
     *     canonical_url_hash:?string,
     *     guid:?string,
     *     guid_hash:string,
     *     excerpt:?string,
     *     content:?string,
     *     image_url:?string,
     *     published_at:\Carbon\CarbonInterface,
     * }  $data
     */
    private function shouldSkipItem(FeedSource $feedSource, array $data, bool $forceNoSkip): bool
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            return true;
        }

        if (filter_var($title, FILTER_VALIDATE_URL)) {
            return true;
        }

        if ($forceNoSkip) {
            return false;
        }

        // For feeds that are explicitly "Breaking only", relax strict URL/host checks.
        // Some urgent feeds omit stable canonical URLs or use different hosts/CDNs.
        // We can still safely de-dupe using guid_hash.
        if ((string) $feedSource->destination === FeedSource::DESTINATION_BREAKING) {
            return false;
        }

        $canonicalUrl = $data['canonical_url'] ?? null;
        if (! $canonicalUrl || ! filter_var((string) $canonicalUrl, FILTER_VALIDATE_URL)) {
            return true;
        }

        $feedHost = parse_url($this->encodeUnicodeUrl($feedSource->url), PHP_URL_HOST) ?: '';
        $canonicalHost = parse_url($this->encodeUnicodeUrl((string) $canonicalUrl), PHP_URL_HOST) ?: '';

        $feedHost = $this->normalizeHost($feedHost);
        $canonicalHost = $this->normalizeHost($canonicalHost);

        if ($feedHost !== '' && $canonicalHost !== '') {
            if ($feedHost === $canonicalHost) {
                return false;
            }

            // Allow simple subdomain differences (e.g. m.example.com vs example.com).
            if (str_ends_with($canonicalHost, '.'.$feedHost) || str_ends_with($feedHost, '.'.$canonicalHost)) {
                return false;
            }

            return true;
        }

        return false;
    }

    private function normalizeHost(?string $host): string
    {
        $host = strtolower(trim((string) $host));
        $host = preg_replace('/^www\./', '', $host) ?: '';

        return $host;
    }

    private function parsePublishedAt(SimpleXMLElement $item): ?Carbon
    {
        $pubDate = isset($item->pubDate) ? trim((string) $item->pubDate) : null;

        if (! $pubDate) {
            $updated = $this->getNamespacedValue($item, 'http://www.w3.org/2005/Atom', 'updated');
            $pubDate = $updated ? trim((string) $updated) : null;
        }

        if (! $pubDate) {
            return null;
        }

        try {
            return Carbon::parse($pubDate)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function getNamespacedValue(SimpleXMLElement $element, string $namespace, string $key): ?string
    {
        $children = $element->children($namespace);
        if (! isset($children->{$key})) {
            return null;
        }

        $value = (string) $children->{$key};

        return trim($value) !== '' ? $value : null;
    }

    private function extractEnclosureImageUrl(SimpleXMLElement $item): ?string
    {
        if (! isset($item->enclosure)) {
            return null;
        }

        $url = (string) ($item->enclosure->attributes()['url'] ?? '');

        return $url !== '' ? $url : null;
    }

    private function extractMediaImageUrl(SimpleXMLElement $item): ?string
    {
        $media = $item->children('http://search.yahoo.com/mrss/');
        $mediaContent = $media->content ?? null;
        $mediaThumbnail = $media->thumbnail ?? null;

        if ($mediaContent) {
            $url = (string) ($mediaContent->attributes()['url'] ?? '');
            $url = trim($url);
            if ($url !== '') {
                return $url;
            }
        }

        if ($mediaThumbnail) {
            $url = (string) ($mediaThumbnail->attributes()['url'] ?? '');
            $url = trim($url);
            if ($url !== '') {
                return $url;
            }
        }

        return null;
    }

    private function extractFirstImageFromHtml(?string $html): ?string
    {
        if (! $html) {
            return null;
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches) !== 1) {
            return null;
        }

        $url = trim($matches[1]);

        return $url !== '' ? $url : null;
    }

    private function makeExcerpt(string $html): ?string
    {
        $plain = $this->normalizeText($html);
        $plain = trim(preg_replace('/\s+/u', ' ', $plain));

        return $plain !== '' ? Str::limit($plain, 240) : null;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function matchesAnyKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            $needle = $this->normalizeText($keyword);
            if ($needle === '') {
                continue;
            }

            if (mb_strpos($text, $needle, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizeText(?string $text): string
    {
        if (! $text) {
            return '';
        }

        $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = strip_tags($decoded);
        $collapsed = preg_replace('/\s+/u', ' ', $stripped) ?: '';

        return mb_strtolower(trim($collapsed), 'UTF-8');
    }

    private function detectLanguage(string $text): string
    {
        return preg_match('/\p{Arabic}/u', $text) === 1 ? 'ar' : 'en';
    }

    private function makeUniqueSlug(string $title): string
    {
        $base = $this->slugify($title);
        $slug = $base;
        $suffix = 2;

        while (Article::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
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

    private function encodeUnicodeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || ! str_contains($url, '://')) {
            return $url;
        }

        $fragment = null;
        if (str_contains($url, '#')) {
            [$url, $fragment] = explode('#', $url, 2);
        }

        $query = null;
        if (str_contains($url, '?')) {
            [$url, $query] = explode('?', $url, 2);
        }

        [$scheme, $rest] = explode('://', $url, 2);
        $scheme = trim($scheme);

        if ($scheme === '' || $rest === '') {
            return $url;
        }

        $slashPos = strpos($rest, '/');
        if ($slashPos === false) {
            $rebuilt = "{$scheme}://{$rest}";

            if ($query !== null) {
                $rebuilt .= "?{$query}";
            }
            if ($fragment !== null) {
                $rebuilt .= "#{$fragment}";
            }

            return $rebuilt;
        }

        $authority = substr($rest, 0, $slashPos);
        $path = substr($rest, $slashPos);

        $segments = explode('/', $path);
        foreach ($segments as $index => $segment) {
            if ($segment === '') {
                continue;
            }

            $segments[$index] = rawurlencode(rawurldecode($segment));
        }

        $encodedPath = implode('/', $segments);

        $rebuilt = "{$scheme}://{$authority}{$encodedPath}";

        if ($query !== null) {
            $rebuilt .= "?{$query}";
        }
        if ($fragment !== null) {
            $rebuilt .= "#{$fragment}";
        }

        return $rebuilt;
    }

    /**
     * @param  array{
     *     title:string,
     *     canonical_url:?string,
     *     canonical_url_hash:?string,
     *     guid:?string,
     *     guid_hash:string,
     *     excerpt:?string,
     *     content:?string,
     *     image_url:?string,
     *     published_at:\Carbon\CarbonInterface,
     * }  $data
     * @return array{
     *     title:string,
     *     canonical_url:?string,
     *     canonical_url_hash:?string,
     *     guid:?string,
     *     guid_hash:string,
     *     excerpt:?string,
     *     content:?string,
     *     image_url:?string,
     *     published_at:\Carbon\CarbonInterface,
     * }
     */
    private function enrichFromWebPageIfNeeded(array $data, ?Article $existing): array
    {
        if (! $data['canonical_url']) {
            return $data;
        }

        $contentText = $this->normalizeText($data['content'] ?: '');
        $excerptText = $this->normalizeText($data['excerpt'] ?: '');

        $existingContentText = $existing ? $this->normalizeText($existing->content ?: '') : '';
        $existingExcerptText = $existing ? $this->normalizeText($existing->excerpt ?: '') : '';

        $needsContent =
            ($existing ? (mb_strlen($existingContentText, 'UTF-8') < 30 && mb_strlen($existingExcerptText, 'UTF-8') < 30) : true)
            && (mb_strlen($contentText, 'UTF-8') < 30 && mb_strlen($excerptText, 'UTF-8') < 30);

        $existingImageUrl = $existing ? trim((string) ($existing->image_url ?? '')) : '';
        $incomingImageUrl = trim((string) ($data['image_url'] ?? ''));

        $needsImage =
            ($existing ? ($existingImageUrl === '' || $this->isPlaceholderImageUrl($existingImageUrl)) : true)
            && ($incomingImageUrl === '' || $this->isPlaceholderImageUrl($incomingImageUrl));

        if (! $needsContent && ! $needsImage) {
            return $data;
        }

        try {
            $scraped = $this->articleScraper->scrape($data['canonical_url']);
        } catch (\Throwable $exception) {
            Log::warning('Article scrape failed', [
                'url' => $data['canonical_url'],
                'exception' => $exception->getMessage(),
            ]);

            return $data;
        }

        if ($needsContent) {
            if (! empty($scraped['content'])) {
                $data['content'] = $scraped['content'];
            }

            if (empty($data['excerpt']) && ! empty($scraped['excerpt'])) {
                $data['excerpt'] = $scraped['excerpt'];
            }
        }

        if ($needsImage && ! empty($scraped['image_url'])) {
            $data['image_url'] = $this->normalizeMaybeRelativeUrl($scraped['image_url'], $data['canonical_url']);
        }

        return $data;
    }

    /**
     * @param  array{
     *     title:string,
     *     canonical_url:?string,
     *     canonical_url_hash:?string,
     *     guid:?string,
     *     guid_hash:string,
     *     excerpt:?string,
     *     content:?string,
     *     image_url:?string,
     *     published_at:\Carbon\CarbonInterface,
     * }  $data
     * @return array{
     *     title:string,
     *     canonical_url:?string,
     *     canonical_url_hash:?string,
     *     guid:?string,
     *     guid_hash:string,
     *     excerpt:?string,
     *     content:?string,
     *     image_url:?string,
     *     published_at:\Carbon\CarbonInterface,
     * }
     */
    private function downloadImageIfPossible(array $data, ?Article $existing): array
    {
        if (
            $existing
            && ! empty($existing->image_url)
            && $this->isLocalUploadUrl((string) $existing->image_url)
            && ! $this->isPlaceholderImageUrl((string) $existing->image_url)
        ) {
            return $data;
        }

        if (
            empty($data['image_url'])
            && $existing
            && ! empty($existing->image_url)
            && ! $this->isPlaceholderImageUrl((string) $existing->image_url)
        ) {
            $data['image_url'] = (string) $existing->image_url;
        }

        $data['image_url'] = $this->normalizeMaybeRelativeUrl($data['image_url'], $data['canonical_url']);

        if (empty($data['image_url']) || ! filter_var((string) $data['image_url'], FILTER_VALIDATE_URL)) {
            return $data;
        }

        if ($this->isPlaceholderImageUrl((string) $data['image_url'])) {
            $data['image_url'] = null;
            return $data;
        }

        $stored = $this->storeRemoteImage((string) $data['image_url'], $data['published_at'], $data['canonical_url']);
        if ($stored) {
            $data['image_url'] = $stored;
        }

        return $data;
    }

    private function isPlaceholderImageUrl(string $url): bool
    {
        $url = strtolower(trim($url));
        if ($url === '') {
            return true;
        }

        if ($this->isLocalUploadUrl($url)) {
            $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
            $name = strtolower(pathinfo($path, PATHINFO_FILENAME));

            if ($name !== '' && in_array($name, $this->placeholderLocalHashes(), true)) {
                return true;
            }
        }

        return str_contains($url, 'default-document-thumbnail')
            || str_contains($url, 'default-document-picture')
            || str_contains($url, 'placeholder');
    }

    /**
     * @return array<int, string>
     */
    private function placeholderLocalHashes(): array
    {
        static $hashes = null;

        if ($hashes !== null) {
            return $hashes;
        }

        $knownPlaceholderUrls = [
            // Lebanon24 defaults (purple templates)
            'https://www.lebanon24.com/uploadImages/DocumentImages/Default-Document-Thumbnail.jpg',
            'https://www.lebanon24.com/uploadImages/DocumentImages/Default-Document-Picture.jpg',
        ];

        $hashes = array_values(array_unique(array_filter(array_map(function (string $url): string {
            return substr(hash('sha256', $this->encodeUnicodeUrl($url)), 0, 24);
        }, $knownPlaceholderUrls))));

        return $hashes;
    }

    private function normalizeMaybeRelativeUrl(?string $url, ?string $baseUrl): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $baseUrl = trim((string) $baseUrl);

        if (str_starts_with($url, '//')) {
            $scheme = $baseUrl !== '' ? (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https') : 'https';

            return "{$scheme}:{$url}";
        }

        if (str_starts_with($url, '/')) {
            $scheme = $baseUrl !== '' ? (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https') : 'https';
            $host = $baseUrl !== '' ? (parse_url($baseUrl, PHP_URL_HOST) ?: '') : '';

            if ($host === '') {
                return null;
            }

            return "{$scheme}://{$host}{$url}";
        }

        if (! str_contains($url, '://') && str_starts_with($url, 'www.')) {
            return "https://{$url}";
        }

        return $this->encodeUnicodeUrl($url);
    }

    private function isLocalUploadUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/uploads/')) {
            return true;
        }

        $baseUrl = (string) config('filesystems.disks.public_uploads.url', '');
        $baseUrl = rtrim($baseUrl, '/');
        if ($baseUrl !== '' && str_starts_with($url, $baseUrl.'/')) {
            return true;
        }

        return false;
    }

    private function storeRemoteImage(string $url, \Carbon\CarbonInterface $publishedAt, ?string $referer): ?string
    {
        $url = trim($url);

        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $url = $this->encodeUnicodeUrl($url);

        $hash = substr(hash('sha256', $url), 0, 24);
        $folder = 'news/'.$publishedAt->format('Y/m');

        $disk = Storage::disk('public_uploads');

        try {
            $disk->makeDirectory($folder);
        } catch (\Throwable) {
            return null;
        }

        $existing = collect(['jpg', 'jpeg', 'png', 'webp'])
            ->map(fn (string $ext) => "{$folder}/{$hash}.{$ext}")
            ->first(fn (string $path) => $disk->exists($path));

        if ($existing) {
            return $disk->url($existing);
        }

        try {
            $headers = [];
            if ($referer && filter_var($referer, FILTER_VALIDATE_URL)) {
                $headers['Referer'] = $referer;
            }

            $response = Http::timeout(25)
                ->withUserAgent('ShaghillaImageFetcher/1.0')
                ->accept('image/*')
                ->withHeaders($headers)
                ->retry(2, 500, function ($exception): bool {
                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        $status = $exception->response?->status();

                        return $status === 429 || ($status !== null && $status >= 500);
                    }

                    return true;
                }, false)
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        $bytes = $response->body();
        if ($bytes === '' || strlen($bytes) > 8 * 1024 * 1024) {
            return null;
        }

        if ($this->looksLikeHtml($bytes)) {
            return null;
        }

        $ext = $this->detectImageExtension($bytes, $contentType, $url);
        if (! $ext) {
            return null;
        }

        $path = "{$folder}/{$hash}.{$ext}";
        try {
            $ok = $disk->put($path, $bytes, ['visibility' => 'public']);
        } catch (\Throwable) {
            return null;
        }

        if ($ok === false) {
            return null;
        }

        return $disk->url($path);
    }

    private function looksLikeHtml(string $bytes): bool
    {
        $head = strtolower(substr(ltrim($bytes), 0, 200));

        return str_starts_with($head, '<!doctype')
            || str_starts_with($head, '<html')
            || str_starts_with($head, '<head')
            || str_starts_with($head, '<script')
            || str_contains($head, '<html');
    }

    private function detectImageExtension(string $bytes, string $contentType, string $url): ?string
    {
        $contentType = strtolower(trim(explode(';', $contentType)[0] ?? ''));

        if (str_contains($contentType, 'png')) {
            return 'png';
        }
        if (str_contains($contentType, 'webp')) {
            return 'webp';
        }
        if (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) {
            return 'jpg';
        }

        // Sniff bytes (fallback when servers misreport Content-Type)
        if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'webp';
        }
        if (strlen($bytes) >= 4 && substr($bytes, 0, 4) === "\x89PNG") {
            return 'png';
        }
        if (strlen($bytes) >= 3 && substr($bytes, 0, 3) === "\xFF\xD8\xFF") {
            return 'jpg';
        }

        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        return in_array($ext, ['jpg', 'png', 'webp'], true) ? $ext : null;
    }
}
