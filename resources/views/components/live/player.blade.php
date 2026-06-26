@props([
    'youtubeUrl' => '',
])

@php
    $embedUrl = \App\Support\YouTube::embedUrl($youtubeUrl);
@endphp

<div class="overflow-hidden rounded-2xl bg-black ring-1 ring-white/10">
    <div class="aspect-video w-full">
        @if ($embedUrl)
            <iframe
                class="h-full w-full"
                src="{{ $embedUrl }}"
                title="YouTube live"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
            ></iframe>
        @else
            <div class="flex h-full w-full items-center justify-center px-6 text-center text-sm text-white/70">
                {{ __('ui.messages.live_url_not_set') }}
            </div>
        @endif
    </div>
</div>
