@php
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
    @if (! empty($article->image_url))
        <meta property="og:image" content="{{ $article->image_url }}" />
        <meta name="twitter:card" content="summary_large_image" />
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

        @if (! empty($article->image_url))
            <div class="overflow-hidden rounded-2xl bg-gray-100 ring-1 ring-black/5">
                <img src="{{ $article->image_url }}" alt="" class="h-auto w-full" loading="lazy" />
            </div>
        @endif

        <div class="rounded-2xl bg-white p-5 leading-relaxed text-gray-900 shadow-sm ring-1 ring-gray-200">
            {!! nl2br(e(trim(strip_tags($article->content ?: $article->excerpt ?: '')))) !!}
        </div>
    </article>
</x-layouts.site>
