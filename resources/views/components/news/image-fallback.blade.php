@props([
    'compact' => false,
])

<div
    aria-hidden="true"
    class="relative flex h-full w-full items-center justify-center overflow-hidden bg-gradient-to-br from-[#edf4f1] via-white to-[#e4edf2]"
>
    <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full border-[18px] border-[#159164]/10"></div>
    <div class="absolute -bottom-14 -left-10 h-40 w-40 rounded-full border-[22px] border-[#d9272e]/10"></div>
    <div class="absolute inset-0 opacity-40 [background-image:radial-gradient(circle_at_center,rgba(14,54,67,0.15)_1px,transparent_1px)] [background-size:18px_18px]"></div>

    <div class="relative flex flex-col items-center text-center text-night">
        <img
            src="{{ asset('website-logo.png') }}"
            alt=""
            @class([
                'object-contain drop-shadow-sm',
                'h-14 w-20' => ! $compact,
                'h-9 w-14' => $compact,
            ])
        />
        <span @class(['font-black', 'mt-2 text-sm' => ! $compact, 'mt-1 text-[11px]' => $compact])>
            رابطة الشغيلة
        </span>
        @unless ($compact)
            <span class="mt-1 text-[11px] font-bold text-ink-muted">آخر الأخبار</span>
        @endunless
    </div>
</div>
