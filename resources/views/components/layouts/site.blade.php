<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" class="overflow-x-hidden">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        <title>{{ $title ?? config('app.name') }}</title>
        <link rel="icon" href="{{ asset('logo-fav.png') }}" type="image/png" />
        <link rel="shortcut icon" href="{{ asset('logo-fav.png') }}" type="image/png" />
        <link rel="apple-touch-icon" href="{{ asset('logo-fav.png') }}" />

        @php
            $deployBuildId = null;
            try {
                $stampPath = base_path('.shaghilla-build-id');
                if (is_file($stampPath)) {
                    $deployBuildId = trim((string) file_get_contents($stampPath)) ?: null;
                }
            } catch (\Throwable) {
                $deployBuildId = null;
            }
        @endphp
        @if ($deployBuildId)
            <meta name="x-shaghilla-build" content="{{ $deployBuildId }}" />
        @endif

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="sh-site-body min-h-screen overflow-x-hidden bg-canvas font-sans text-ink antialiased">
        @if ($deployBuildId)
            <!-- shaghilla-build: {{ $deployBuildId }} -->
        @endif
        <x-site.header />
        <x-news.breaking-ticker />

        <main class="sh-site-main mx-auto w-full max-w-6xl px-4 pb-10 pt-6">
            {{ $slot }}
        </main>

        <x-site.footer />

        <script>
            (() => {
                const prefetched = new Set();
                const prefetch = (link) => {
                    const url = link?.href;
                    if (!url || prefetched.has(url)) return;
                    prefetched.add(url);
                    fetch(url, { credentials: 'omit', priority: 'low' }).catch(() => prefetched.delete(url));
                };

                document.addEventListener('pointerover', (event) => {
                    const link = event.target.closest?.('[data-prefetch-page]');
                    if (link) prefetch(link);
                }, { passive: true });
                document.addEventListener('focusin', (event) => {
                    const link = event.target.closest?.('[data-prefetch-page]');
                    if (link) prefetch(link);
                });
                document.addEventListener('touchstart', (event) => {
                    const link = event.target.closest?.('[data-prefetch-page]');
                    if (link) prefetch(link);
                }, { passive: true });
            })();
        </script>
    </body>
</html>
