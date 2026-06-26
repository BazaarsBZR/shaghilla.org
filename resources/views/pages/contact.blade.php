@php
    $formLabels = $formLabels ?? [];
@endphp

<x-layouts.site :title="$title ?? __('ui.pages.contact')">
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
