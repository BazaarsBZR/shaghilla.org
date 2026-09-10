@php
    $formLabels = $formLabels ?? [];
@endphp

<x-layouts.site :title="$title ?? __('ui.pages.contact')">
    <style>a[href="https://wa.me/96179333415"] { display: none !important; }</style>
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="space-y-2">
            <h1 class="text-2xl font-extrabold tracking-tight text-ink">{{ $title ?? __('ui.pages.contact') }}</h1>
            <p class="text-sm leading-relaxed text-ink-muted">
                {{ $intro ?? __('ui.contact.intro_default') }}
            </p>
        </div>

        @if (session('success'))
            <div class="sh-alert-success">
                {{ session('success') }}
            </div>
        @endif

        <a
            href="https://wa.me/96179333415"
            target="_blank"
            rel="noopener noreferrer"
            class="group flex items-center justify-between gap-4 rounded-2xl bg-[#0f7b55] p-5 text-white shadow-overlay transition hover:bg-[#0b6948] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#25d366]/40"
        >
            <span class="flex items-center gap-3">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15 ring-1 ring-white/25">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12.04 2a9.84 9.84 0 0 0-8.42 14.92L2 22l5.22-1.58A9.9 9.9 0 1 0 12.04 2Zm0 17.98a8.05 8.05 0 0 1-4.1-1.12l-.3-.18-3.1.94.96-3.02-.2-.31a8.07 8.07 0 1 1 6.74 3.69Zm4.43-6.04c-.24-.12-1.44-.71-1.66-.79-.22-.08-.38-.12-.54.12-.16.24-.62.79-.76.95-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.94-1.2a7.25 7.25 0 0 1-1.34-1.66c-.14-.24-.01-.37.11-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.3-.74-1.78-.2-.47-.4-.41-.54-.42h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.69 2.58 4.1 3.62.57.25 1.02.4 1.37.51.58.18 1.1.16 1.51.1.46-.07 1.44-.59 1.64-1.16.2-.57.2-1.06.14-1.16-.06-.1-.22-.16-.46-.28Z" />
                    </svg>
                </span>
                <span class="text-right">
                    <span class="block text-base font-extrabold">تواصل معنا عبر واتساب</span>
                    <span class="mt-1 block text-sm font-semibold text-white/80" dir="ltr">+961 79 333 415</span>
                </span>
            </span>
            <svg class="h-5 w-5 shrink-0 transition group-hover:-translate-x-1" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M15.79 10.75a.75.75 0 0 0 0-1.5H6.02l3.22-3.22a.75.75 0 0 0-1.06-1.06l-4.5 4.5a.75.75 0 0 0 0 1.06l4.5 4.5a.75.75 0 1 0 1.06-1.06l-3.22-3.22h9.77Z" clip-rule="evenodd" />
            </svg>
        </a>

        <form method="POST" action="{{ route('contact.store') }}" class="sh-form-shell">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="sh-form-label" for="name">{{ $formLabels['name'] ?? __('ui.contact.name') }}</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        required
                        class="sh-form-field"
                    />
                    @error('name')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="sh-form-label" for="email">{{ $formLabels['email'] ?? __('ui.contact.email') }}</label>
                    <input
                        id="email"
                        name="email"
                        type="tel"
                        inputmode="tel"
                        value="{{ old('email') }}"
                        required
                        class="sh-form-field"
                    />
                    @error('email')
                        <div class="sh-form-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div>
                <label class="sh-form-label" for="subject">{{ $formLabels['subject'] ?? __('ui.contact.subject') }}</label>
                <input
                    id="subject"
                    name="subject"
                    type="text"
                    value="{{ old('subject') }}"
                    required
                    class="sh-form-field"
                />
                @error('subject')
                    <div class="sh-form-error">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="sh-form-label" for="message">{{ $formLabels['message'] ?? __('ui.contact.message') }}</label>
                <textarea
                    id="message"
                    name="message"
                    rows="6"
                    required
                    class="sh-form-field"
                >{{ old('message') }}</textarea>
                @error('message')
                    <div class="sh-form-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="hidden">
                <input id="website" name="website" type="text" value="{{ old('website') }}" autocomplete="off" tabindex="-1" />
            </div>

            <button
                type="submit"
                class="sh-btn-primary w-full"
            >
                {{ $formLabels['submit'] ?? __('ui.contact.send') }}
            </button>
        </form>
    </div>
</x-layouts.site>
