<x-layouts.site :title="__('ui.pages.home')">
    <style>
        .sh-news-card, .sh-news-hero { min-width: 0; }
        .sh-news-card-title, .sh-news-card-summary { overflow-wrap: anywhere; }
        @media (max-width: 639px) {
            .sh-home-stack { display: grid; gap: 1rem; }
            .sh-home-stack.space-y-8 > :not([hidden]) ~ :not([hidden]) { margin-top: 0; }
            .sh-home-lead { gap: .75rem; }
            .sh-news-hero { border-radius: 18px; }
            .sh-news-hero-media { min-height: 230px; aspect-ratio: 4 / 3; }
            .sh-news-hero-content { padding: 14px; }
            .sh-news-hero-title { display: -webkit-box; overflow: hidden; font-size: 1.18rem; line-height: 1.55; -webkit-box-orient: vertical; -webkit-line-clamp: 3; }
            .sh-news-card { border-radius: 16px; }
            .sh-news-card.has-media .sh-news-card-link { display: grid; grid-template-columns: 112px minmax(0, 1fr); min-height: 116px; }
            .sh-news-card-media { width: 112px; height: 100%; min-height: 116px; aspect-ratio: auto; }
            .sh-news-card-content { gap: 7px; padding: 11px 12px; }
            .sh-news-card-meta { font-size: 10px; }
            .sh-news-card-title { font-size: 14px; line-height: 1.55; -webkit-line-clamp: 3; }
            .sh-news-card-summary { display: none; }
            .sh-news-card-footer { font-size: 10px; }
            .sh-news-card-footer > :last-child { display: none; }
            .sh-news-card.is-text-only .sh-news-card-content { min-height: 132px; padding: 15px; }
            .sh-home-section-heading { align-items: center; }
            .sh-home-section-heading h2 { font-size: 1.1rem; }
            .sh-latest-grid + nav { gap: 6px; }
            .sh-latest-grid + nav a, .sh-latest-grid + nav span { min-width: 38px; padding-inline: 9px; }
        }
        @media (max-width: 374px) {
            .sh-news-card.has-media .sh-news-card-link { grid-template-columns: 96px minmax(0, 1fr); }
            .sh-news-card-media { width: 96px; }
            .sh-news-card-title { font-size: 13px; }
        }
    </style>
    <div class="sh-home-stack space-y-8">
        @php
            $newsLayout = in_array(($newsLayout ?? ''), ['mosaic', 'classic', 'grid'], true) ? $newsLayout : 'mosaic';
            $newsTopSmallCount = max(0, min(4, (int) ($newsTopSmallCount ?? 4)));

            $hasReadableStory = static function ($article): bool {
                $text = trim((string) preg_replace(
                    '/[[:space:]]+/u',
                    ' ',
                    strip_tags((string) ($article->content ?: $article->excerpt ?: '')),
                ));

                return \Illuminate\Support\Str::length($text) >= 45;
            };

            $heroArticle = $newsLayout === 'grid' || empty($hero) || ! $hasReadableStory($hero) || blank($hero->image_url)
                ? null
                : $hero;
            $topGridArticles = collect($topGridArticles ?? collect())
                ->filter($hasReadableStory)
                ->take($newsTopSmallCount)
                ->values();
            $latestArticles = collect($latestArticles ?? collect())
                ->filter($hasReadableStory)
                ->take(9)
                ->values();
        @endphp

        @if ($newsLayout === 'mosaic')
            @if ($heroArticle || $topGridArticles->isNotEmpty())
                @if ($heroArticle && $topGridArticles->isNotEmpty())
                    <section dir="ltr" class="sh-home-lead flex flex-col gap-4 md:flex-row-reverse md:items-stretch">
                        <div dir="rtl" class="md:basis-7/12 md:shrink-0">
                            <x-news.hero-card :article="$heroArticle" :fill="true" />
                        </div>

                        <div dir="rtl" class="md:basis-5/12 md:shrink-0">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:h-full md:auto-rows-fr">
                                @foreach ($topGridArticles as $article)
                                    <x-news.article-card :article="$article" />
                                @endforeach
                            </div>
                        </div>
                    </section>
                @elseif ($heroArticle)
                    <div class="sh-home-lead"><x-news.hero-card :article="$heroArticle" /></div>
                @elseif ($topGridArticles->isNotEmpty())
                    <div class="sh-home-lead grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach ($topGridArticles as $article)
                            <x-news.article-card :article="$article" />
                        @endforeach
                    </div>
                @endif
            @endif
        @elseif ($newsLayout === 'classic')
            @if ($heroArticle)
                <div class="sh-home-lead"><x-news.hero-card :article="$heroArticle" /></div>
            @endif
        @endif

        <section
            id="latest-news"
            class="sh-home-latest space-y-4"
            style="content-visibility: auto; contain-intrinsic-size: 900px;"
            x-data="{
                loading: false,
                endpoint: @js(route('home.latest')),
                async requestPage(page, fallbackUrl = null) {
                    if (this.loading || !Number.isFinite(page) || page < 1) return;

                    this.loading = true;

                    try {
                        const url = new URL(this.endpoint, window.location.origin);
                        url.searchParams.set('latest_page', String(page));

                        const response = await fetch(url.toString(), {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                        });

                        if (!response.ok) {
                            throw new Error('Failed to load latest news page.');
                        }

                        const payload = await response.json();
                        if (!payload?.html) {
                            throw new Error('Latest news response did not include HTML.');
                        }

                        this.$refs.latestResults.innerHTML = payload.html;

                        const browserUrl = new URL(window.location.href);
                        browserUrl.searchParams.set('latest_page', String(payload.page ?? page));
                        window.history.replaceState({}, '', `${browserUrl.pathname}${browserUrl.search}#latest-news`);

                        const sectionTop = this.$el.getBoundingClientRect().top + window.scrollY - 96;
                        window.scrollTo({ top: Math.max(sectionTop, 0), behavior: 'smooth' });
                    } catch (error) {
                        if (fallbackUrl) {
                            window.location.assign(fallbackUrl);
                        }
                    } finally {
                        this.loading = false;
                    }
                },
                handlePaginationClick(event) {
                    const link = event.target.closest('a[data-latest-page]');
                    if (!link) return;

                    event.preventDefault();
                    const page = Number(link.dataset.latestPage || 1);
                    this.requestPage(page, link.href);
                },
            }"
            @click="handlePaginationClick($event)"
        >
            <div class="sh-home-section-heading flex items-end justify-between gap-3 border-b border-line pb-3">
                <div>
                    <span>تغطية مستمرة</span>
                    <h2 class="text-xl font-extrabold tracking-tight text-ink">{{ __('ui.labels.latest_news') }}</h2>
                </div>
                <span
                    x-cloak
                    x-show="loading"
                    class="text-xs font-semibold text-ink-muted"
                >
                    ...
                </span>
            </div>

            <div x-ref="latestResults">
                @include('partials.home.latest-news-results', [
                    'latestArticles' => $latestArticles,
                    'latestPaginator' => $latestPaginator ?? null,
                ])
            </div>
        </section>

        @if (! empty($membershipSection['enabled']))
            <x-home.cta-hero
                :title="$membershipSection['title'] ?? ''"
                :body="$membershipSection['body'] ?? ''"
                :button-label="$membershipSection['button_label'] ?? ''"
                :button-url="$membershipSection['button_href'] ?? route('membership')"
                :image-url="$membershipSection['image_url'] ?? ''"
                :style="$membershipSection['style'] ?? 'dark'"
            />
        @endif

        @if (! empty($contactSection['enabled']))
            <x-home.cta-hero
                :title="$contactSection['title'] ?? ''"
                :body="$contactSection['body'] ?? ''"
                :button-label="$contactSection['button_label'] ?? ''"
                :button-url="$contactSection['button_href'] ?? route('contact')"
                :image-url="$contactSection['image_url'] ?? ''"
                :style="$contactSection['style'] ?? 'light'"
            />
        @endif

        @if (! empty($homeVideosEnabled))
            <x-home.media-section
                :title="$homeVideosTitle ?? null"
                :items="$homeVideos ?? collect()"
                :layout="$homeVideosLayout ?? 'grid'"
            />
        @endif
    </div>
    <x-site.whatsapp-fab />
</x-layouts.site>
