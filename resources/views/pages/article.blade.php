@php
    $mediaUrl = trim((string) ($article->image_url ?? ''));
    $isVideo = preg_match('/\.(mp4|webm|mov|m4v)(?:$|[?#])/i', $mediaUrl) === 1;
    $metaDescription = \Illuminate\Support\Str::limit(
        trim(preg_replace('/\\s+/u', ' ', strip_tags($article->excerpt ?: $article->content ?: ''))),
        160,
        '',
    );
@endphp

@push('head')
    @if ($metaDescription !== '')
        <meta name="description" content="{{ $metaDescription }}" />
    @endif
    <link rel="canonical" href="{{ $article->canonical_url ?: request()->url() }}" />

    <meta property="og:type" content="article" />
    <meta property="og:title" content="{{ $article->title }}" />
    @if ($metaDescription !== '')
        <meta property="og:description" content="{{ $metaDescription }}" />
    @endif
    <meta property="og:url" content="{{ $article->canonical_url ?: request()->url() }}" />
    @if ($mediaUrl !== '' && ! $isVideo)
        <meta property="og:image" content="{{ $mediaUrl }}" />
        <meta name="twitter:card" content="summary_large_image" />
    @elseif ($isVideo)
        <meta property="og:video" content="{{ $mediaUrl }}" />
        <meta property="og:video:type" content="video/mp4" />
        <meta name="twitter:card" content="player" />
    @else
        <meta name="twitter:card" content="summary" />
    @endif
@endpush

<x-layouts.site :title="$article->title">
    <article class="space-y-6">
        <header class="space-y-3">
            <h1 class="text-2xl font-extrabold leading-snug text-gray-900 sm:text-3xl">{{ $article->title }}</h1>

            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
                <span>{{ __('ui.labels.published_at') }}: {{ optional($article->published_at)->format('Y-m-d H:i') }}</span>
                @if ($article->feedSource?->name)
                    <span class="opacity-60">•</span>
                    <span>{{ __('ui.labels.source') }}: {{ $article->feedSource->name }}</span>
                @endif
            </div>
        </header>

        @if ($isVideo)
            <div class="overflow-hidden rounded-2xl bg-black ring-1 ring-black/10">
                <video src="{{ $mediaUrl }}" controls playsinline preload="metadata" class="max-h-[75vh] w-full bg-black">
                    Your browser does not support video playback.
                </video>
            </div>
        @elseif ($mediaUrl !== '')
            <div class="overflow-hidden rounded-2xl bg-gray-100 ring-1 ring-black/5">
                <img src="{{ $mediaUrl }}" alt="" class="h-auto w-full" loading="lazy" referrerpolicy="no-referrer" />
            </div>
        @endif

        <div class="sh-article-body rounded-2xl bg-white p-5 leading-relaxed text-gray-900 shadow-sm ring-1 ring-gray-200 sm:p-8">
            @if ($article->feed_source_id)
                {!! nl2br(e(trim(strip_tags($article->content ?: $article->excerpt ?: '')))) !!}
            @else
                {!! str($article->content ?: $article->excerpt ?: '')->sanitizeHtml() !!}
            @endif
        </div>
    </article>
</x-layouts.site>
