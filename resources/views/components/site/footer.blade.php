<footer class="sh-site-footer border-t border-line bg-surface">
    <div class="mx-auto w-full max-w-6xl px-4 py-10 text-sm text-ink-muted">
        @php
            $brandName = \App\Models\SiteSetting::getValue('site_brand_name', config('app.name'));
            $footerItems = \App\Models\SitePage::footerMenu();

            $showSocials = \App\Models\SiteSetting::getBool('footer_show_socials', false);
            $facebookUrl = trim((string) \App\Models\SiteSetting::getValue('footer_facebook_url', ''));
            $xUrl = trim((string) \App\Models\SiteSetting::getValue('footer_x_url', ''));
            $instagramUrl = trim((string) \App\Models\SiteSetting::getValue('footer_instagram_url', ''));
            $hasSocials = $showSocials && ($facebookUrl !== '' || $xUrl !== '' || $instagramUrl !== '');
        @endphp

        <div class="grid gap-8 border-b border-line pb-8 {{ $hasSocials ? 'md:grid-cols-3' : 'md:grid-cols-2' }}">
            <div class="space-y-3">
                <div class="flex items-center gap-3 text-lg font-extrabold tracking-tight text-ink">
                    <img src="{{ asset('website-logo.png') }}" alt="" class="h-12 w-12 rounded-full bg-white object-contain p-1 shadow-sm" />
                    <span>{{ $brandName }}</span>
                </div>
                <p class="max-w-xs text-sm leading-relaxed text-ink-muted">
                    صوت الناس وأخبار لبنان في منصة عربية واضحة، سريعة، ومتجددة.
                </p>
            </div>

            <div class="space-y-3 md:justify-self-center">
                <div class="grid grid-cols-2 gap-x-6 gap-y-3 font-semibold">
                    @if ($footerItems->isEmpty())
                        <a href="{{ route('home') }}" class="text-ink-muted transition hover:text-ink">{{ __('ui.nav.home') }}</a>
                        <a href="{{ route('live') }}" class="text-ink-muted transition hover:text-ink">{{ __('ui.nav.live') }}</a>
                        <a href="{{ route('membership') }}" class="text-ink-muted transition hover:text-ink">{{ __('ui.nav.membership') }}</a>
                        <a href="{{ route('contact') }}" class="text-ink-muted transition hover:text-ink">{{ __('ui.nav.contact') }}</a>
                    @else
                        @foreach ($footerItems as $item)
                            <a
                                href="{{ $item->url() }}"
                                class="text-ink-muted transition hover:text-ink"
                                @if ($item->target()) target="{{ $item->target() }}" rel="{{ $item->rel() }}" @endif
                            >
                                {{ $item->displayTitle() }}
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>

            @if ($hasSocials)
                <div class="space-y-5 md:justify-self-end">
                    <div class="space-y-2">
                        <div class="text-xs font-extrabold uppercase tracking-wide text-ink">
                            {{ __('ui.footer.follow_us') }}
                        </div>
                        <div class="flex items-center gap-3">
                            @if ($facebookUrl !== '')
                                <a
                                    href="{{ $facebookUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-pill border border-line bg-surface text-ink-muted shadow-surface transition hover:bg-surface-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
                                >
                                    <span class="sr-only">{{ __('ui.footer.facebook') }}</span>
                                    <span class="text-sm font-black">f</span>
                                </a>
                            @endif

                            @if ($xUrl !== '')
                                <a
                                    href="{{ $xUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-pill border border-line bg-surface text-ink-muted shadow-surface transition hover:bg-surface-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
                                >
                                    <span class="sr-only">{{ __('ui.footer.x') }}</span>
                                    <span class="text-sm font-black">X</span>
                                </a>
                            @endif

                            @if ($instagramUrl !== '')
                                <a
                                    href="{{ $instagramUrl }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-pill border border-line bg-surface text-ink-muted shadow-surface transition hover:bg-surface-soft hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
                                >
                                    <span class="sr-only">{{ __('ui.footer.instagram') }}</span>
                                    <span class="text-xs font-black">IG</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 pt-5 text-xs font-semibold text-ink-faint">
            <div>© {{ now()->year }}</div>
            <div>{{ $brandName }}</div>
        </div>
    </div>
</footer>
