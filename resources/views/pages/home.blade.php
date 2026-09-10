<x-layouts.site :title="__('ui.pages.home')">
    <div class="space-y-6">
        @php
            $newsLayout = in_array(($newsLayout ?? ''), ['mosaic', 'classic', 'grid'], true) ? $newsLayout : 'mosaic';
            $newsTopSmallCount = max(0, min(12, (int) ($newsTopSmallCount ?? 4)));

            $heroArticle = $newsLayout === 'grid' ? null : (! empty($hero) ? $hero : null);
            $topGridArticles = collect($topGridArticles ?? collect())->take($newsTopSmallCount)->values();
            $latestArticles = collect($latestArticles ?? collect())->values();
        @endphp

        @if ($newsLayout === 'mosaic')
            @if ($heroArticle || $topGridArticles->isNotEmpty())
                @if ($heroArticle && $topGridArticles->isNotEmpty())
                    <section dir="ltr" class="flex flex-col gap-4 md:flex-row-reverse md:items-stretch">
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
                    <x-news.hero-card :article="$heroArticle" />
                @elseif ($topGridArticles->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($topGridArticles as $article)
                            <x-news.article-card :article="$article" />
                        @endforeach
                    </div>
                @endif
            @endif
        @elseif ($newsLayout === 'classic')
            @if ($heroArticle)
                <x-news.hero-card :article="$heroArticle" />
            @endif
        @endif

        <section
            id="latest-news"
            class="space-y-3"
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
            <div class="flex items-center justify-between gap-3 border-b border-line pb-2">
                <h2 class="text-base font-extrabold tracking-tight text-ink">{{ __('ui.labels.latest_news') }}</h2>
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
