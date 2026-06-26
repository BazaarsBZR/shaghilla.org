@php
    /** @var string $icon */
    $icon = (string) ($icon ?? '');
@endphp

<div class="flex items-center gap-2">
    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gray-100 text-gray-800">
        <x-icons.social :name="$icon" class="h-5 w-5" />
    </span>
    <span class="text-sm text-gray-600">{{ $icon }}</span>
</div>

