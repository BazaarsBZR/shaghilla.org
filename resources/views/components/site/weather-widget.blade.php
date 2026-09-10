@props([
    'label' => 'لبنان',
    'endpoint' => null,
])

@php
    $endpoint = $endpoint ?: route('api.weather');
@endphp

<div
    class="relative inline-flex"
    dir="rtl"
    x-data="{
        endpoint: @js($endpoint),
        label: @js($label),
        temperature: null,
        weatherCode: null,
        loading: true,
        open: false,
        areasLoading: false,
        areas: [],
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
            const key = 'shaghilla_weather.header.v3';
            const ttlMs = 10 * 60 * 1000;

            try {
                const cachedRaw = localStorage.getItem(key);
                if (cachedRaw) {
                    const cached = JSON.parse(cachedRaw);
                    if (
                        cached?.ts
                        && cached?.data
                        && Number.isFinite(Number(cached.data.temperature_c))
                        && (Date.now() - cached.ts) < ttlMs
                    ) {
                        this.temperature = cached.data.temperature_c ?? null;
                        this.weatherCode = cached.data.weather_code ?? null;
                        this.label = cached.data.label ?? this.label;
                        this.loading = false;
                        return;
                    }
                }
            } catch (_) {}

            try {
                let weatherEndpoint = this.endpoint;
                let selectedArea = null;

                try {
                    selectedArea = JSON.parse(localStorage.getItem('shaghilla_weather.selection.v1') || 'null');
                } catch (_) {}

                if (selectedArea?.latitude && selectedArea?.longitude) {
                    const url = new URL(this.endpoint, window.location.origin);
                    url.searchParams.set('country', 'LB');
                    url.searchParams.set('city', String(selectedArea.key || ''));
                    url.searchParams.set('latitude', String(selectedArea.latitude));
                    url.searchParams.set('longitude', String(selectedArea.longitude));
                    weatherEndpoint = url.toString();
                } else try {
                    const locationResponse = await fetch('https://ipapi.co/json/', {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (locationResponse.ok) {
                        const location = await locationResponse.json();
                        const latitude = Number(location?.latitude);
                        const longitude = Number(location?.longitude);

                        if (
                            String(location?.country_code || '').toUpperCase() === 'LB'
                            && Number.isFinite(latitude)
                            && Number.isFinite(longitude)
                        ) {
                            const url = new URL(this.endpoint, window.location.origin);
                            url.searchParams.set('country', 'LB');
                            url.searchParams.set('city', String(location?.city || ''));
                            url.searchParams.set('latitude', String(latitude));
                            url.searchParams.set('longitude', String(longitude));
                            weatherEndpoint = url.toString();
                        }
                    }
                } catch (_) {
                    // Location is optional; the configured Lebanon weather remains the fallback.
                }

                const res = await fetch(weatherEndpoint, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('bad_status');
                const data = await res.json();
                this.temperature = data?.temperature_c ?? null;
                this.weatherCode = data?.weather_code ?? null;
                this.label = data?.label ?? this.label;
                if (Number.isFinite(Number(data?.temperature_c))) {
                    try {
                        localStorage.setItem(key, JSON.stringify({ ts: Date.now(), data }));
                    } catch (_) {}
                }
            } catch (_) {
                // ignore (keep fallback UI)
            } finally {
                this.loading = false;
            }
        },
        async loadAreas() {
            if (this.areas.length || this.areasLoading) return;
            this.areasLoading = true;
            try {
                const url = new URL(this.endpoint, window.location.origin);
                url.searchParams.set('areas', '1');
                const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('bad_status');
                const data = await response.json();
                this.areas = Array.isArray(data?.areas) ? data.areas : [];
            } catch (_) {
                this.areas = [];
            } finally {
                this.areasLoading = false;
            }
        },
        selectArea(area) {
            this.label = area.label;
            this.temperature = area.temperature_c;
            this.weatherCode = area.weather_code;
            this.open = false;
            try {
                localStorage.setItem('shaghilla_weather.selection.v1', JSON.stringify(area));
                localStorage.setItem('shaghilla_weather.header.v3', JSON.stringify({
                    ts: Date.now(),
                    data: { label: area.label, temperature_c: area.temperature_c, weather_code: area.weather_code },
                }));
            } catch (_) {}
        },
    }"
    x-init="load()"
    @click.outside="open = false"
>
    <button
        type="button"
        class="inline-flex items-center justify-center gap-1.5 rounded-control border border-line bg-surface-soft px-2 py-1.5 text-[11px] font-extrabold text-ink shadow-surface transition hover:border-emerald-300 hover:bg-white sm:gap-2 sm:px-3 sm:py-2 sm:text-xs"
        @click="open = !open; if (open) loadAreas()"
        :aria-expanded="open"
        aria-label="اختيار منطقة لعرض الطقس"
    >
        <span class="text-sm leading-none" x-text="iconFor(weatherCode)"></span>
        <span class="hidden whitespace-nowrap text-ink-muted sm:inline" x-text="label"></span>
        <span class="hidden text-line sm:inline">•</span>
        <span class="tabular-nums text-ink" x-text="tempText"></span>
        <svg class="hidden h-3.5 w-3.5 text-ink-muted transition sm:block" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.origin.top.left
        class="absolute left-0 top-full z-[90] mt-2 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-line bg-white text-right shadow-2xl"
    >
        <div class="flex items-center justify-between border-b border-line bg-[#0e2d3b] px-4 py-3 text-white">
            <div><p class="text-sm font-black">الطقس في لبنان</p><p class="mt-0.5 text-[10px] text-white/65">اختر المنطقة التي تريد متابعتها</p></div>
            <button type="button" class="rounded-full p-1 text-white/70 hover:bg-white/10 hover:text-white" @click="open = false" aria-label="إغلاق">✕</button>
        </div>
        <div class="max-h-[min(25rem,70vh)] overflow-y-auto p-2">
            <div x-show="areasLoading" class="px-3 py-8 text-center text-xs text-ink-muted">جارٍ تحميل طقس المناطق…</div>
            <div x-show="!areasLoading && !areas.length" class="px-3 py-8 text-center text-xs text-ink-muted">تعذّر تحميل المناطق الآن. حاول مرة أخرى.</div>
            <div x-show="areas.length" class="grid grid-cols-2 gap-1.5">
                <template x-for="area in areas" :key="area.key">
                    <button type="button" class="flex items-center justify-between rounded-xl border border-transparent bg-surface-soft px-3 py-2.5 text-right transition hover:border-emerald-200 hover:bg-emerald-50" @click="selectArea(area)">
                        <span class="truncate text-xs font-black text-ink" x-text="area.label"></span>
                        <span class="mr-2 inline-flex shrink-0 items-center gap-1 text-xs font-black text-emerald-700"><span x-text="iconFor(area.weather_code)"></span><span class="tabular-nums" x-text="Number.isFinite(Number(area.temperature_c)) ? Math.round(Number(area.temperature_c)) + '°' : '—'"></span></span>
                    </button>
                </template>
            </div>
        </div>
        <a href="https://open-meteo.com/" target="_blank" rel="noopener noreferrer" class="block border-t border-line px-4 py-2 text-[10px] font-bold text-ink-muted hover:text-ink">بيانات الطقس: Open‑Meteo</a>
    </div>
</div>
