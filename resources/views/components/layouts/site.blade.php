<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" class="overflow-x-hidden">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        <title>{{ $title ?? config('app.name') }}</title>

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

        <link href="{{ asset('vendor/bladewind/css/animate.min.css') }}" rel="stylesheet" />
        <link href="{{ asset('vendor/bladewind/css/bladewind-ui.min.css') }}" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
    </head>
    <body class="min-h-screen overflow-x-hidden bg-canvas font-sans text-ink antialiased">
        @if ($deployBuildId)
            <!-- shaghilla-build: {{ $deployBuildId }} -->
        @endif
        <x-site.header />
        <x-news.breaking-ticker />

        <main class="mx-auto w-full max-w-6xl px-4 pb-8 pt-5">
            {{ $slot }}
        </main>

        <x-site.footer />

        <script src="{{ asset('vendor/bladewind/js/helpers.js') }}" type="text/javascript"></script>
    </body>
</html>
