@props([
    'article' => null,
    'url' => null,
    'title' => null,
    'variant' => 'light', // light|dark
])

@php
    $platforms = \App\Models\SharePlatform::activeCached();
    $shareUrl = $article
        ? secure_url(route('news.show', $article->slug, false))
        : secure_url(request()->path());
    $shareTitle = (string) ($title ?: ($article->title ?? config('app.name', '')));
@endphp

<div
    class="relative"
        x-data="{
            open: false,
            copied: false,
            title: @js($shareTitle),
            url: @js($shareUrl),
        async nativeShare() {
            if (!navigator.share) return false;
            try {
                await navigator.share({ title: this.title, url: this.url });
                this.open = false;
                return true;
            } catch (e) {
                // If the user cancels the share sheet, treat it as handled.
                if (e?.name === 'AbortError') return true;
                return false;
            }
        },
        async copyLink() {
            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(this.url);
                } else {
                    window.prompt('Copy link:', this.url);
                }
                this.copied = true;
                setTimeout(() => (this.copied = false), 1500);
            } catch (e) {}
        },
        openUrl(href) {
            try {
                const w = window.open(href, '_blank');
                if (w) w.opener = null;
            } catch (e) {}
        }
    }"
    @click.outside="open = false"
>
    <button
        type="button"
        @click.prevent.stop="(async () => { const ok = await nativeShare(); if (!ok) open = !open; })()"
        class="inline-flex items-center justify-center rounded-md p-1 transition"
        @class([
            'text-ink-muted hover:text-ink hover:bg-surface-soft' => $variant === 'light',
            'text-white/85 hover:text-white hover:bg-white/10' => $variant === 'dark',
        ])
        aria-label="{{ __('ui.actions.share') }}"
        title="{{ __('ui.actions.share') }}"
    >
        <x-icons.share class="h-4 w-4" />
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.right
        class="absolute right-0 z-20 mt-2 w-56 overflow-hidden rounded-card border border-line bg-surface shadow-overlay"
        dir="rtl"
        @click.stop
    >
        <div class="px-3 py-2 text-xs font-extrabold text-ink-muted">
            {{ __('ui.actions.share') }}
        </div>

        <div class="border-t border-line/70">
            @foreach ($platforms as $platform)
                @php
                    $href = $platform->buildShareUrl($shareUrl, $shareTitle);
                    $platformName = (string) $platform->name;
                    $icon = (string) ($platform->icon ?? '');
                @endphp

                @if ($href)
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-ink hover:bg-surface-soft"
                        @click.prevent.stop="openUrl(@js($href))"
                    >
                        <span class="flex items-center gap-2">
                            <x-icons.social :name="$icon" class="h-4 w-4" />
                            <span>{{ $platformName }}</span>
                        </span>
                        <span class="text-xs text-ink-faint">↗</span>
                    </button>
                @elseif ($platform->use_native_share)
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-ink hover:bg-surface-soft"
                        @click.prevent.stop="nativeShare()"
                    >
                        <span class="flex items-center gap-2">
                            <x-icons.social :name="$icon" class="h-4 w-4" />
                            <span>{{ $platformName }}</span>
                        </span>
                        <span class="text-xs text-ink-faint">{{ __('ui.actions.native_share') }}</span>
                    </button>
                @endif
            @endforeach

            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-ink hover:bg-surface-soft"
                @click.prevent.stop="copyLink()"
            >
                <span class="flex items-center gap-2">
                    <span class="inline-flex h-4 w-4 items-center justify-center text-[12px] leading-none">🔗</span>
                    <span>{{ __('ui.actions.copy_link') }}</span>
                </span>
                <span class="text-xs text-ink-faint" x-show="copied">{{ __('ui.messages.link_copied') }}</span>
            </button>

            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-sm font-semibold text-ink hover:bg-surface-soft"
                x-show="navigator.share"
                @click.prevent.stop="nativeShare()"
            >
                <span class="flex items-center gap-2">
                    <span class="inline-flex h-4 w-4 items-center justify-center text-[12px] leading-none">📲</span>
                    <span>{{ __('ui.actions.native_share') }}</span>
                </span>
            </button>
        </div>
    </div>
</div>
