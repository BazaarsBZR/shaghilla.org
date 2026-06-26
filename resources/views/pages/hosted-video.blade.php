@push('head')
    <link rel="canonical" href="{{ request()->url() }}" />
@endpush

@php
    $posterUrl = $video->posterUrl();
    $videoUrl = $video->videoUrl();
@endphp

<x-layouts.site :title="$video->title">
    <article class="mx-auto max-w-5xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-right text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl">
                {{ $video->title }}
            </h1>
            @if ($video->published_at)
                <div class="text-right text-sm font-semibold text-gray-600">
                    {{ $video->published_at->format('Y-m-d') }}
                </div>
            @endif
        </header>

        <div class="overflow-hidden rounded-2xl bg-black ring-1 ring-black/10">
            <video
                class="h-auto w-full"
                controls
                playsinline
                preload="metadata"
                @if ($posterUrl) poster="{{ $posterUrl }}" @endif
            >
                <source src="{{ $videoUrl }}" />
                متصفحك لا يدعم تشغيل الفيديو.
            </video>
        </div>

        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('home') }}" class="text-sm font-extrabold text-gray-900 hover:underline">
                {{ __('ui.nav.home') }}
            </a>

            <div class="flex items-center gap-3">
                <x-news.share-menu :url="request()->url()" :title="$video->title" />

                <a href="{{ $videoUrl }}" class="text-sm font-semibold text-gray-700 hover:underline" download>
                    تحميل
                </a>
            </div>
        </div>
    </article>
</x-layouts.site>
