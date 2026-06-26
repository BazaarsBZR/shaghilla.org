<x-layouts.site :title="$title ?? ($page?->displayTitle() ?? config('app.name'))">
    <article class="mx-auto max-w-3xl space-y-6">
        <header class="space-y-2">
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">
                {{ $page->displayTitle() }}
            </h1>
        </header>

        <div
            class="max-w-none space-y-4 text-sm leading-relaxed text-gray-800 [&_a]:text-gray-900 [&_a]:underline [&_blockquote]:border-r-4 [&_blockquote]:border-gray-200 [&_blockquote]:pr-4 [&_blockquote]:text-gray-700 [&_h2]:text-lg [&_h2]:font-extrabold [&_h3]:text-base [&_h3]:font-extrabold [&_li]:mb-2 [&_ol]:list-decimal [&_ol]:pr-6 [&_p]:leading-relaxed [&_ul]:list-disc [&_ul]:pr-6"
        >
            @if (app()->getLocale() === 'en' && ! empty($page->content_html_en))
                {!! $page->content_html_en !!}
            @else
                {!! $page->content_html_ar !!}
            @endif
        </div>
    </article>
</x-layouts.site>
