<header class="sh-site-header border-b border-line bg-surface" x-data="{ open: false }">
    @php
        $brandName = \App\Models\SiteSetting::getValue('site_brand_name', config('app.name'));
        $showHeaderLogo = \App\Models\SiteSetting::getBool('header_logo_enabled', true);
        $headerLogoUrl = trim((string) \App\Models\SiteSetting::getValue('header_logo_url', ''));
        $headerLogoUrl = $headerLogoUrl !== '' ? $headerLogoUrl : asset('website-logo.png');

        $showLiveButton = \App\Models\SiteSetting::getBool('header_show_live_button', true);
        $showHeaderSearch = \App\Models\SiteSetting::getBool('header_search_enabled', false);
        $showHeaderLanguageChips = \App\Models\SiteSetting::getBool('header_language_chips_enabled', false);
        $showHeaderWeather = \App\Models\SiteSetting::getBool('header_weather_enabled', true);
        $headerWeatherLabel = \App\Models\SiteSetting::getValue('header_weather_label_ar', 'لبنان');

        $menuItems = \App\Models\SitePage::headerMenu();

        $rawLayout = (string) \App\Models\SiteSetting::getValue('header_layout_json', '');
        $layout = null;
        try {
            $decoded = $rawLayout !== '' ? json_decode($rawLayout, true, 512, JSON_THROW_ON_ERROR) : null;
            $layout = is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            $layout = null;
        }

        $row1Enabled = (bool) ($layout['row1_enabled'] ?? false);
        $row1 = is_array(($layout['row1'] ?? null)) ? $layout['row1'] : [];
        $row2 = is_array(($layout['row2'] ?? null)) ? $layout['row2'] : [];

        $zone = function (array $row, string $name): array {
            $value = $row[$name] ?? [];
            return is_array($value) ? $value : [];
        };

        $row1Right = $zone($row1, 'right');
        $row1Center = $zone($row1, 'center');
        $row1Left = $zone($row1, 'left');

        $row2Right = $zone($row2, 'right');
        $row2Center = $zone($row2, 'center');
        $row2Left = $zone($row2, 'left');

        if (! $layout) {
            $row1Enabled = false;
            $row1Right = [];
            $row1Center = [];
            $row1Left = [];

            $row2Right = [
                ['type' => 'logo', 'data' => []],
                ['type' => 'menu', 'data' => []],
            ];
            $row2Center = [];
            $row2Left = [
                ['type' => 'live', 'data' => []],
                ['type' => 'hamburger', 'data' => []],
            ];
        }

        $hasType = function (string $needle, array ...$zones): bool {
            foreach ($zones as $z) {
                foreach ($z as $block) {
                    $type = is_array($block) ? (string) ($block['type'] ?? '') : '';
                    if ($type === $needle) {
                        return true;
                    }
                }
            }

            return false;
        };

        $hasHamburger = $hasType('hamburger', $row1Right, $row1Center, $row1Left, $row2Right, $row2Center, $row2Left);
        if (! $hasHamburger) {
            $row2Left[] = ['type' => 'hamburger', 'data' => []];
        }

        $hasLive = $hasType('live', $row1Right, $row1Center, $row1Left, $row2Right, $row2Center, $row2Left);

        $hasWeather = $hasType('weather', $row1Right, $row1Center, $row1Left, $row2Right, $row2Center, $row2Left);
        if ($showHeaderWeather && ! $hasWeather) {
            array_unshift($row2Left, ['type' => 'weather', 'data' => []]);
        }
    @endphp

    @php
        $renderBlock = function (array $block) use (
            $brandName,
            $headerLogoUrl,
            $headerWeatherLabel,
            $menuItems,
            $showHeaderLanguageChips,
            $showHeaderLogo,
            $showHeaderSearch,
            $showLiveButton,
            $showHeaderWeather,
        ) {
            $type = (string) ($block['type'] ?? '');

            switch ($type) {
                case 'logo':
                    echo '<a href="'.e(route('home')).'" class="sh-site-brand flex min-w-0 items-center gap-2 text-base font-extrabold tracking-tight text-ink">';
                    if ($showHeaderLogo) {
                        echo '<img src="'.e($headerLogoUrl).'" alt="'.e($brandName).'" class="h-8 w-8 shrink-0 object-contain" />';
                    }
                    echo '<span class="whitespace-nowrap">'.e($brandName).'</span>';
                    echo '</a>';
                    break;

                case 'menu':
                    echo '<nav class="sh-primary-nav hidden min-w-0 items-center gap-1 text-[13px] font-extrabold text-ink xl:flex">';
                    if ($menuItems->isEmpty()) {
                        echo '<a href="'.e(route('home')).'" class="inline-flex items-center py-2 text-ink-muted transition hover:text-ink">'.e(__('ui.nav.home')).'</a>';
                        if (! $showLiveButton) {
                            echo '<a href="'.e(route('live')).'" class="inline-flex items-center py-2 text-ink-muted transition hover:text-ink">'.e(__('ui.nav.live')).'</a>';
                        }
                    } else {
                        foreach ($menuItems as $item) {
                            if (in_array($item->url(), [route('membership'), route('contact')], true)) {
                                continue;
                            }
                            if (
                                $showLiveButton
                                && $item->type === \App\Models\SitePage::TYPE_ROUTE
                                && $item->route_name === 'live'
                            ) {
                                continue;
                            }
                            $attrs = '';
                            if ($item->target()) {
                                $attrs = ' target="'.e($item->target()).'" rel="'.e($item->rel()).'"';
                            }
                            echo '<a href="'.e($item->url()).'" class="inline-flex items-center py-2 text-ink-muted transition hover:text-ink"'.$attrs.'>'.e($item->displayTitle()).'</a>';
                        }
                    }
                    echo '<a data-prefetch-page href="'.e(route('public-money.index')).'" class="sh-nav-link '.(request()->routeIs('public-money.*') ? 'is-active' : '').'">'.e(__('ui.nav.public_money')).'</a>';
                    echo '<a data-prefetch-page href="'.e(route('financial-status.index')).'" class="sh-nav-link '.(request()->routeIs('financial-status.*') ? 'is-active' : '').'">الوضع المالي</a>';
                    echo '<a data-prefetch-page href="'.e(route('government-tenders.index')).'" class="sh-nav-link '.(request()->routeIs('government-tenders.*') ? 'is-active' : '').'">المناقصات الحكومية</a>';
                    echo '</nav>';
                    break;

                case 'live':
                    if ($showLiveButton && $hasLive) {
                        echo '<a href="'.e(route('live')).'" class="inline-flex shrink-0 items-center justify-center rounded-control bg-accent px-3 py-1.5 text-[11px] font-extrabold text-white shadow-surface transition hover:bg-accent-strong focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/30 sm:px-4 sm:py-2 sm:text-sm">';
                        echo '<span class="sm:hidden">'.e(__('ui.nav.live')).'</span>';
                        echo '<span class="hidden sm:inline">'.e('البث المباشر').'</span>';
                        echo '</a>';
                    }
                    break;

                case 'hamburger':
                    echo '<button type="button" class="inline-flex items-center justify-center rounded-control border border-line bg-surface p-2 text-ink transition hover:bg-surface-soft xl:hidden" @click="open = !open" :aria-expanded="open.toString()" aria-controls="mobile-menu">';
                    echo '<span class="sr-only">'.e(__('ui.actions.toggle_menu')).'</span>';
                    echo '<svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
                    echo '<path fill-rule="evenodd" d="M3 5h14a1 1 0 0 1 0 2H3a1 1 0 0 1 0-2Zm0 6h14a1 1 0 1 1 0 2H3a1 1 0 0 1 0-2Zm0 6h14a1 1 0 1 1 0 2H3a1 1 0 0 1 0-2Z" clip-rule="evenodd" />';
                    echo '</svg>';
                    echo '</button>';
                    break;

                case 'search':
                    if ($showHeaderSearch) {
                        echo '<div class="relative w-full max-w-sm">';
                        echo '<form method="GET" action="'.e(route('search')).'" class="relative">';
                        echo '<input name="q" type="search" placeholder="'.e(__('ui.search.placeholder')).'" class="w-full rounded-control border border-line bg-surface py-2 pr-9 pl-3 text-sm font-semibold text-ink shadow-surface outline-none focus:border-accent/35 focus:ring-2 focus:ring-accent/15" />';
                        echo '<svg class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">';
                        echo '<path fill-rule="evenodd" d="M9 3a6 6 0 1 0 3.865 10.597l2.769 2.769a1 1 0 0 0 1.414-1.414l-2.769-2.769A6 6 0 0 0 9 3Zm-4 6a4 4 0 1 1 8 0 4 4 0 0 1-8 0Z" clip-rule="evenodd" />';
                        echo '</svg>';
                        echo '</form>';
                        echo '</div>';
                    }
                    break;

                case 'language_chips':
                    if ($showHeaderLanguageChips) {
                        echo '<div class="flex items-center gap-2">';
                        echo '<a href="#" class="inline-flex items-center justify-center rounded-control border border-line bg-surface-soft px-2 py-1 text-[11px] font-extrabold text-ink-muted hover:text-ink">EN</a>';
                        echo '<a href="#" class="inline-flex items-center justify-center rounded-control border border-line bg-surface-soft px-2 py-1 text-[11px] font-extrabold text-ink-muted hover:text-ink">ES</a>';
                        echo '</div>';
                    }
                    break;

                case 'weather':
                    if ($showHeaderWeather) {
                        echo view('components.site.weather-widget', [
                            'label' => $headerWeatherLabel ?: 'لبنان',
                        ])->render();
                    }
                    break;

                case 'spacer':
                    echo '<span class="inline-block w-3 sm:w-6"></span>';
                    break;
            }
        };
    @endphp

    @if ($row1Enabled)
        <div class="border-b border-line bg-surface-soft/50">
            <div class="mx-auto w-full max-w-[1500px] px-4 py-2.5">
                <div class="flex items-center gap-3" dir="rtl">
                    <div class="flex items-center gap-2">
                        @foreach ($row1Right as $block)
                            @php($renderBlock($block))
                        @endforeach
                    </div>
                    <div class="flex flex-1 items-center justify-center gap-2">
                        @foreach ($row1Center as $block)
                            @php($renderBlock($block))
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2">
                        @foreach ($row1Left as $block)
                            @php($renderBlock($block))
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Main nav row -->
    <div class="bg-surface">
        <div class="mx-auto w-full max-w-[1500px] px-4 py-2.5">
            <div class="flex items-center gap-2 sm:gap-4" dir="rtl">
                <div class="flex min-w-0 flex-1 items-center gap-3 sm:gap-4">
                    @foreach ($row2Right as $block)
                        @php($renderBlock($block))
                    @endforeach
                </div>
                <div class="flex flex-1 items-center justify-center gap-2 sm:gap-3">
                    @foreach ($row2Center as $block)
                        @php($renderBlock($block))
                    @endforeach
                </div>
                <div class="flex items-center gap-1.5 sm:gap-2">
                    @foreach ($row2Left as $block)
                        @php($renderBlock($block))
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div id="mobile-menu" class="border-t border-line bg-surface xl:hidden" x-show="open" x-transition x-cloak>
        <nav class="mx-auto grid w-full max-w-[1500px] gap-2 px-4 py-4 text-sm font-semibold text-ink sm:grid-cols-2 lg:grid-cols-3">
            @if ($menuItems->isEmpty())
                <a href="{{ route('home') }}" class="rounded-control px-3 py-2 text-ink-muted hover:bg-surface-soft hover:text-ink">{{ __('ui.nav.home') }}</a>
                @if (! $showLiveButton)
                    <a href="{{ route('live') }}" class="rounded-control px-3 py-2 text-ink-muted hover:bg-surface-soft hover:text-ink">{{ __('ui.nav.live') }}</a>
                @endif
            @else
                @foreach ($menuItems as $item)
                    @continue(in_array($item->url(), [route('membership'), route('contact')], true))
                    @continue(
                        $showLiveButton
                            && $item->type === \App\Models\SitePage::TYPE_ROUTE
                            && $item->route_name === 'live'
                    )
                    <a
                        href="{{ $item->url() }}"
                        class="rounded-control px-3 py-2 text-ink-muted hover:bg-surface-soft hover:text-ink"
                        @if ($item->target()) target="{{ $item->target() }}" rel="{{ $item->rel() }}" @endif
                    >
                        {{ $item->displayTitle() }}
                    </a>
                @endforeach
            @endif

            <a data-prefetch-page href="{{ route('public-money.index') }}" class="rounded-control px-3 py-2 text-ink-muted hover:bg-surface-soft hover:text-ink">{{ __('ui.nav.public_money') }}</a>
            <a data-prefetch-page href="{{ route('financial-status.index') }}" class="rounded-control px-3 py-2 text-ink-muted hover:bg-surface-soft hover:text-ink">الوضع المالي</a>
            <a data-prefetch-page href="{{ route('government-tenders.index') }}" class="rounded-control px-3 py-2 text-ink-muted hover:bg-surface-soft hover:text-ink">المناقصات الحكومية</a>

            @if ($showLiveButton && $hasLive)
                <a
                    href="{{ route('live') }}"
                    class="mt-2 inline-flex items-center justify-center rounded-control bg-accent px-4 py-2 text-sm font-extrabold text-white shadow-surface hover:bg-accent-strong"
                >
                    البث المباشر
                </a>
            @endif
        </nav>
    </div>
</header>

<style>
    .sh-site-header {
        position: relative;
        z-index: 60;
        box-shadow: 0 1px 0 rgba(16, 39, 53, .06), 0 10px 30px rgba(16, 39, 53, .035);
    }
    .sh-primary-nav > a,
    .sh-nav-link {
        display: inline-flex;
        align-items: center;
        min-height: 38px;
        padding: 0 11px;
        border-radius: 12px;
        color: #536176;
        white-space: nowrap;
        text-decoration: none;
        transition: color 160ms ease, background 160ms ease, transform 160ms ease;
    }
    .sh-primary-nav > a:hover,
    .sh-nav-link:hover {
        color: #102735;
        background: #f1f6f4;
        transform: translateY(-1px);
    }
    .sh-nav-link.is-active {
        color: #086b4b;
        background: #e7f4ee;
        box-shadow: inset 0 0 0 1px rgba(8, 124, 85, .14);
    }
    @media (max-width: 1279px) {
        .sh-site-header [id="mobile-menu"] a {
            border: 1px solid #e5ebed;
            background: #f8faf9;
        }
    }
    @media (max-width: 639px) {
        .sh-site-header > .bg-surface > div { padding-inline: 10px; }
        .sh-site-header > .bg-surface > div > div { gap: 7px; }
        .sh-site-brand { gap: 6px; font-size: 13px; }
        .sh-site-brand img { width: 28px; height: 28px; }
        .sh-weather-trigger { min-width: 56px; height: 38px; padding-inline: 7px; }
        .sh-weather-panel { position: fixed; top: 68px; right: 10px; left: 10px; width: auto; max-height: calc(100vh - 82px); }
    }
    @media (max-width: 374px) {
        .sh-site-brand span { max-width: 82px; overflow: hidden; text-overflow: ellipsis; }
        .sh-weather-trigger { min-width: 48px; }
        .sh-weather-trigger .tabular-nums { display: none; }
    }
</style>
