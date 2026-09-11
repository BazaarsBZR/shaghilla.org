@php
    $mediaUrl = trim((string) ($article->image_url ?? ''));
    $isVideo = preg_match('/\.(mp4|webm|mov|m4v)(?:$|[?#])/i', $mediaUrl) === 1;
    $articleText = trim((string) preg_replace('/[[:space:]]+/u', ' ', strip_tags((string) ($article->content ?: $article->excerpt ?: ''))));
    $hasReadableBody = \Illuminate\Support\Str::length($articleText) >= 45;
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
    <link rel="canonical" href="{{ secure_url(request()->path()) }}" />

    <meta property="og:type" content="article" />
    <meta property="og:title" content="{{ $article->title }}" />
    @if ($metaDescription !== '')
        <meta property="og:description" content="{{ $metaDescription }}" />
    @endif
    <meta property="og:url" content="{{ secure_url(request()->path()) }}" />
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
    <article class="mx-auto max-w-4xl space-y-6">
        <a href="{{ route('home') }}#latest-news" class="inline-flex items-center gap-2 text-sm font-extrabold text-accent transition hover:text-night">
            <span aria-hidden="true">→</span>
            العودة إلى آخر الأخبار
        </a>

        <header class="overflow-hidden rounded-[2rem] bg-gradient-to-br from-night via-[#123f46] to-[#19654f] p-6 text-white shadow-overlay sm:p-10">
            <div class="mb-5 flex flex-wrap items-center gap-2 text-xs font-bold text-white/75">
                @if ($article->feedSource?->name)
                    <span class="rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15">{{ $article->feedSource->name }}</span>
                @endif
                <span class="rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15">{{ optional($article->published_at)->format('Y-m-d H:i') }}</span>
            </div>

            <h1 class="text-3xl font-black leading-[1.45] sm:text-5xl">{{ $article->title }}</h1>
        </header>

        @if ($isVideo)
            <div class="overflow-hidden rounded-2xl bg-black ring-1 ring-black/10">
                <video src="{{ $mediaUrl }}" controls playsinline preload="metadata" class="max-h-[75vh] w-full bg-black">
                    Your browser does not support video playback.
                </video>
            </div>
        @elseif ($mediaUrl !== '')
            <div class="overflow-hidden rounded-[2rem] bg-gray-100 shadow-surface ring-1 ring-black/5">
                <img src="{{ $mediaUrl }}" alt="{{ $article->title }}" width="960" height="540" class="h-auto w-full" decoding="async" referrerpolicy="no-referrer" />
            </div>
        @endif

        @if ($hasReadableBody)
            <div class="sh-article-body rounded-[2rem] border border-line bg-white p-6 text-lg leading-9 text-ink shadow-surface sm:p-10">
                @if ($article->feed_source_id)
                    {!! nl2br(e($articleText)) !!}
                @else
                    {!! str($article->content ?: $article->excerpt ?: '')->sanitizeHtml() !!}
                @endif
            </div>
        @else
            <div class="rounded-[2rem] border border-amber-200 bg-amber-50 p-6 text-amber-950 sm:p-8">
                <h2 class="text-xl font-black">هذا الخبر ورد كعنوان موجز</h2>
                <p class="mt-2 leading-7">لم ينشر المصدر نصاً كاملاً يمكن عرضه هنا. لا نضيف تفاصيل غير موجودة في المصدر.</p>
            </div>
        @endif

    </article>
</x-layouts.site>
