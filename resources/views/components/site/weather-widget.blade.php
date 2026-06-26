@props([
    'label' => 'لبنان',
    'endpoint' => null,
])

@php
    $endpoint = $endpoint ?: route('api.weather');
@endphp

<div
    class="inline-flex items-center justify-center gap-1.5 rounded-control border border-line bg-surface-soft px-2 py-1.5 text-[11px] font-extrabold text-ink shadow-surface sm:gap-2 sm:px-3 sm:py-2 sm:text-xs"
    dir="rtl"
    x-data="{
        endpoint: @js($endpoint),
        label: @js($label),
        temperature: null,
        weatherCode: null,
        loading: true,
        iconFor(code) {
            if (code === null || code === undefined) return '☀️';
            const c = Number(code);
            if (c === 0) return '☀️';
            if ([1,2,3].includes(c)) return '⛅';
            if ([45,48].includes(c)) return '🌫️';
            if ([51,53,55,56,57].includes(c)) return '🌦️';
            if ([61,63,65,66,67].includes(c)) return '🌧️';
            if ([71,73,75,77].includes(c)) return '❄️';
            if ([80,81,82].includes(c)) return '🌧️';
            if ([95,96,99].includes(c)) return '⛈️';
            return '☀️';
        },
        get tempText() {
            if (this.temperature === null || this.temperature === undefined) return '—';
            const v = Math.round(Number(this.temperature));
            if (!Number.isFinite(v)) return '—';
            return `${v}°C`;
        },
        async load() {
            const key = 'shaghilla_weather_header_v1';
            const ttlMs = 10 * 60 * 1000;

            try {
                const cachedRaw = localStorage.getItem(key);
                if (cachedRaw) {
                    const cached = JSON.parse(cachedRaw);
                    if (cached?.ts && cached?.data && (Date.now() - cached.ts) < ttlMs) {
                        this.temperature = cached.data.temperature_c ?? null;
                        this.weatherCode = cached.data.weather_code ?? null;
                        this.label = cached.data.label ?? this.label;
                        this.loading = false;
                        return;
                    }
                }
            } catch (_) {}

            try {
                const res = await fetch(this.endpoint, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('bad_status');
                const data = await res.json();
                this.temperature = data?.temperature_c ?? null;
                this.weatherCode = data?.weather_code ?? null;
                this.label = data?.label ?? this.label;
                try {
                    localStorage.setItem(key, JSON.stringify({ ts: Date.now(), data }));
                } catch (_) {}
            } catch (_) {
                // ignore (keep fallback UI)
            } finally {
                this.loading = false;
            }
        },
    }"
    x-init="load()"
>
    <span class="text-sm leading-none" x-text="iconFor(weatherCode)"></span>
    <span class="hidden whitespace-nowrap text-ink-muted sm:inline" x-text="label"></span>
    <span class="hidden text-line sm:inline">•</span>
    <span class="tabular-nums text-ink" x-text="tempText"></span>
</div>
