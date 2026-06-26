@props([
    'title' => '',
    'body' => '',
    'buttonLabel' => '',
    'buttonUrl' => '#',
    'imageUrl' => '',
    'style' => 'light',
])

@php
    $style = in_array($style, ['light', 'dark', 'red'], true) ? $style : 'light';

    $containerClass = match ($style) {
        'dark' => 'bg-night text-white ring-night-line/45',
        'red' => 'bg-accent text-white ring-accent-strong/30',
        default => 'bg-surface text-ink ring-line',
    };

    $titleClass = match ($style) {
        'dark', 'red' => 'text-white',
        default => 'text-ink',
    };

    $bodyClass = match ($style) {
        'dark' => 'text-white/80',
        'red' => 'text-white/90',
        default => 'text-ink-muted',
    };

    $buttonClass = match ($style) {
        'red' => 'bg-white text-accent hover:bg-white/95 focus-visible:ring-white/30',
        'dark' => 'bg-white text-night hover:bg-white/95 focus-visible:ring-white/30',
        default => 'bg-ink text-white hover:bg-ink/90 focus-visible:ring-accent/25',
    };

    $hasImage = is_string($imageUrl) && trim($imageUrl) !== '';
@endphp

<section class="overflow-hidden rounded-2xl {{ $containerClass }} ring-1 shadow-surface">
    <div class="grid gap-6 p-6 sm:p-8 {{ $hasImage ? 'lg:grid-cols-2 lg:items-center' : '' }}">
        <div class="space-y-3 text-right">
            @if ($title !== '')
                <h2 class="text-xl font-extrabold tracking-tight {{ $titleClass }} sm:text-2xl">
                    {{ $title }}
                </h2>
            @endif

            @if ($body !== '')
                <p class="text-sm leading-relaxed {{ $bodyClass }}">
                    {{ $body }}
                </p>
            @endif

            @if ($buttonLabel !== '' && $buttonUrl !== '')
                <div>
                    <a
                        href="{{ $buttonUrl }}"
                        class="inline-flex items-center justify-center rounded-control px-5 py-3 text-sm font-extrabold shadow-surface transition focus:outline-none focus-visible:ring-2 {{ $buttonClass }}"
                    >
                        {{ $buttonLabel }}
                    </a>
                </div>
            @endif
        </div>

        @if ($hasImage)
            <div class="overflow-hidden rounded-2xl bg-surface-soft/20 ring-1 ring-white/10">
                <img src="{{ $imageUrl }}" alt="" class="h-auto w-full object-cover" loading="lazy" />
            </div>
        @endif
    </div>
</section>
