@props([
    'items' => null,
])

@php
    $enabled = \App\Models\SiteSetting::getBool('breaking_ticker_enabled', true);
    $dividerEnabled = \App\Models\SiteSetting::getBool('breaking_ticker_divider_logo_enabled', true);
    $dividerLogoUrl = trim((string) \App\Models\SiteSetting::getValue('breaking_ticker_divider_logo_url', ''));
    $dividerLogoUrl = $dividerLogoUrl !== '' ? $dividerLogoUrl : asset('logo-fav.png');

    $tickerLimit = (int) (\App\Models\SiteSetting::getValue('ticker_limit', '10') ?? 10);
    $tickerLimit = max(1, min(50, $tickerLimit));

    $pauseOnHover = \App\Models\SiteSetting::getBool('breaking_ticker_pause_on_hover', true);

    // New JS ticker settings (inspired by https://github.com/yeg2799/breaking-news-ticker)
    $engine = \App\Models\SiteSetting::getValue('breaking_ticker_engine', 'js');
    $engine = in_array($engine, ['js', 'legacy'], true) ? $engine : 'js';

    $direction = \App\Models\SiteSetting::getValue('breaking_ticker_direction', 'left');
    $direction = in_array($direction, ['left', 'right'], true) ? $direction : 'left';

    $speedPxPerSec = \App\Models\SiteSetting::getInt('breaking_ticker_speed_px_per_sec', 90);
    $speedPxPerSec = max(20, min(600, $speedPxPerSec));

    $gapPx = \App\Models\SiteSetting::getInt('breaking_ticker_gap_px', 20);
    $gapPx = max(0, min(80, $gapPx));

    $pollEnabled = \App\Models\SiteSetting::getBool('breaking_ticker_poll_enabled', false);
    $pollIntervalSeconds = \App\Models\SiteSetting::getInt('breaking_ticker_poll_interval_seconds', 45);
    $pollIntervalSeconds = max(15, min(300, $pollIntervalSeconds));

    $resolvedItems = collect();
    if ($enabled) {
        $resolvedItems = $items;
        if (! ($resolvedItems instanceof \Illuminate\Support\Collection)) {
            $resolvedItems = \Illuminate\Support\Facades\Cache::remember(
                'news.breaking.ticker',
                now()->addMinutes(5),
                fn () => \App\Models\Article::query()
                    ->where('status', 'published')
                    ->where('is_breaking', true)
                    ->orderByDesc('imported_at')
                    ->orderByDesc('published_at')
                    ->orderByDesc('id')
                    ->limit($tickerLimit)
                    ->get(),
            );
        }
    }

    $initialItems = $resolvedItems->map(function ($article): array {
        return [
            'slug' => (string) $article->slug,
            'title' => (string) $article->title,
            'time' => optional($article->published_at)->format('H:i') ?: null,
        ];
    })->values()->all();

    $apiUrl = route('api.breaking', ['limit' => $tickerLimit]);
    $newsBaseUrl = url('/news');
@endphp

@if ($enabled)
    <div class="mx-auto w-full max-w-6xl px-4">
        <div class="border-b border-line bg-surface py-2.5">
            <div
                data-breaking-ticker
                data-enabled="1"
                data-engine="{{ $engine }}"
                data-api-url="{{ $apiUrl }}"
                data-news-base-url="{{ $newsBaseUrl }}"
                data-divider-enabled="{{ $dividerEnabled ? '1' : '0' }}"
                data-divider-logo-url="{{ $dividerLogoUrl }}"
                data-pause-on-hover="{{ $pauseOnHover ? '1' : '0' }}"
                data-direction="{{ $direction }}"
                data-speed-px-per-sec="{{ $speedPxPerSec }}"
                data-gap-px="{{ $gapPx }}"
                data-poll-enabled="{{ $pollEnabled ? '1' : '0' }}"
                data-poll-interval-seconds="{{ $pollIntervalSeconds }}"
                class="sh-breaking-ticker flex items-center gap-3"
                dir="rtl"
            >
                <div class="shrink-0 rounded-pill bg-accent px-3 py-1.5 text-[11px] font-extrabold leading-none text-white">
                    {{ __('ui.labels.breaking') }}
                </div>

                <span class="hidden h-5 w-px shrink-0 bg-line sm:block" aria-hidden="true"></span>

                <div class="flex-1 min-w-0">
                    @if ($resolvedItems->isNotEmpty())
                        <div data-breaking-ticker-viewport class="relative overflow-hidden" dir="ltr">
                            <div data-breaking-ticker-track class="sh-breaking-track text-sm text-ink"></div>
                        </div>

                        <script type="application/json" data-breaking-ticker-items>
                            @json($initialItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        </script>

                        <noscript>
                            <div class="overflow-x-auto whitespace-nowrap text-sm text-ink" dir="rtl">
                                @foreach ($resolvedItems as $article)
                                    <a href="{{ route('news.show', $article->slug) }}" class="inline-flex items-center gap-2 px-2 font-bold hover:underline">
                                        <span class="text-xs font-semibold text-ink-muted">{{ optional($article->published_at)->format('H:i') }}</span>
                                        <span class="font-semibold">{{ $article->title }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </noscript>
                    @else
                        <div class="text-sm font-semibold text-ink-muted" dir="rtl">
                            {{ __('ui.messages.no_breaking_news') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
