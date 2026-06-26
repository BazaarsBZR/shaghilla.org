<x-layouts.site :title="__('ui.pages.live')">
    @php
        $selectedYoutubeId = $selectedYoutubeId ?? null;
        $selectedEmbedUrl = $selectedYoutubeId ? \App\Support\YouTube::embedUrl($selectedYoutubeId) : null;
        $liveThumb = ! empty($liveYoutubeId) ? "https://img.youtube.com/vi/{$liveYoutubeId}/hqdefault.jpg" : null;
    @endphp

    <div
        class="rounded-2xl bg-night p-4 text-white ring-1 ring-night-line/45 sm:p-6"
        x-data="{
            selectedId: @js($selectedYoutubeId),
            embedUrl: @js($selectedEmbedUrl),
            select(id) {
                if (!id) return;
                this.selectedId = id;
                this.embedUrl = `https://www.youtube.com/embed/${id}?rel=0&autoplay=1`;
                const url = new URL(window.location.href);
                url.searchParams.set('v', id);
                window.history.replaceState({}, '', url);
            },
        }"
    >
        <div class="mb-6 flex items-end justify-between gap-4">
            <h1 class="text-2xl font-extrabold tracking-tight">{{ __('ui.pages.live') }}</h1>
        </div>

        <div class="flex flex-col gap-6 lg:flex-row-reverse lg:items-start">
            <div class="flex-1">
                <div class="overflow-hidden rounded-2xl bg-night ring-1 ring-night-line/45">
                    <div class="aspect-video w-full">
                        @if ($selectedEmbedUrl)
                            <iframe
                                class="h-full w-full"
                                src="{{ $selectedEmbedUrl }}"
                                x-bind:src="embedUrl"
                                title="YouTube player"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                                allowfullscreen
                            ></iframe>
                        @else
                            <div class="flex h-full w-full items-center justify-center px-6 text-center text-sm text-white/75">
                                {{ __('ui.messages.live_url_not_set') }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="lg:w-80">
                <div class="rounded-2xl bg-night-surface/80 ring-1 ring-night-line/45">
                    <div class="border-b border-night-line/40 px-4 py-3 text-sm font-extrabold text-white">
                        الفيديو
                    </div>

                    <div class="max-h-[520px] overflow-y-auto p-2">
                        @if (! empty($liveYoutubeId))
                            <a
                                href="{{ route('live', ['v' => $liveYoutubeId]) }}"
                                class="w-full rounded-xl px-3 py-3 text-right hover:bg-night-line/20"
                                @click.prevent="select(@js($liveYoutubeId))"
                                :class="selectedId === @js($liveYoutubeId) ? 'bg-night-line/30 ring-1 ring-night-line/45' : ''"
                            >
                                <div class="flex items-center gap-3" dir="rtl">
                                    <div class="relative h-14 w-24 shrink-0 overflow-hidden rounded-lg bg-night/80 ring-1 ring-night-line/45">
                                        @if ($liveThumb)
                                            <img src="{{ $liveThumb }}" alt="" loading="lazy" class="h-full w-full object-cover opacity-90" />
                                        @endif
                                        <div class="absolute left-2 top-2 rounded bg-accent px-2 py-1 text-[10px] font-extrabold leading-none text-white">
                                            LIVE
                                        </div>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="line-clamp-2 text-sm font-extrabold text-white">{{ __('ui.pages.live') }}</div>
                                                <div class="mt-1 flex items-center justify-between gap-2 text-xs font-semibold text-white/75">
                                                    <x-news.share-menu :url="route('live', ['v' => $liveYoutubeId])" :title="__('ui.pages.live')" variant="dark" />
                                                    <div>{{ __('ui.nav.live') }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                @endif

                        @forelse (($playlistVideos ?? collect()) as $item)
                            @php
                                $youtubeId = \App\Support\YouTube::extractId($item->youtube_url);
                                $thumbnail = $item->thumbnail_url ?: ($youtubeId ? "https://img.youtube.com/vi/{$youtubeId}/hqdefault.jpg" : null);
                            @endphp
                            @continue(! $youtubeId)

                            <a
                                href="{{ route('live', ['v' => $youtubeId]) }}"
                                class="mt-2 w-full rounded-xl px-3 py-3 text-right hover:bg-night-line/20"
                                @click.prevent="select(@js($youtubeId))"
                                :class="selectedId === @js($youtubeId) ? 'bg-night-line/30 ring-1 ring-night-line/45' : ''"
                            >
                                <div class="flex items-center gap-3" dir="rtl">
                                    <div class="h-14 w-24 shrink-0 overflow-hidden rounded-lg bg-night/80 ring-1 ring-night-line/45">
                                        @if ($thumbnail)
                                            <img src="{{ $thumbnail }}" alt="" loading="lazy" class="h-full w-full object-cover opacity-90" />
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <div class="line-clamp-2 text-sm font-extrabold text-white">{{ $item->title }}</div>
                                        <div class="mt-1 flex items-center justify-between gap-2 text-xs font-semibold text-white/75">
                                            <x-news.share-menu :url="route('live', ['v' => $youtubeId])" :title="(string) $item->title" variant="dark" />
                                            @if ($item->published_at)
                                                <div>{{ $item->published_at->format('Y-m-d') }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="px-4 py-6 text-center text-sm text-white/75">
                                لا توجد فيديوهات حالياً.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.site>
