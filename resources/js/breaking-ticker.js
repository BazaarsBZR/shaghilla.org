const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

function safeJsonParse(text, fallback) {
    try {
        return JSON.parse(text);
    } catch {
        return fallback;
    }
}

function buildItemNode({ slug, title, time }, { newsBaseUrl }) {
    const a = document.createElement('a');
    a.className = 'sh-breaking-item';
    a.href = `${newsBaseUrl}/${slug}`;
    a.dir = 'rtl';

    const timeSpan = document.createElement('span');
    timeSpan.className = 'sh-breaking-time';
    timeSpan.textContent = time || '';

    const titleSpan = document.createElement('span');
    titleSpan.className = 'sh-breaking-title';
    titleSpan.textContent = title || '';

    a.appendChild(timeSpan);
    a.appendChild(titleSpan);
    return a;
}

function buildDividerNode(dividerLogoUrl) {
    const img = document.createElement('img');
    img.className = 'sh-breaking-divider';
    img.alt = '';
    img.loading = 'lazy';
    img.decoding = 'async';
    img.src = dividerLogoUrl;
    return img;
}

function getItemsFromScript(container) {
    const script = container.querySelector('script[data-breaking-ticker-items]');
    if (!script) return [];
    return safeJsonParse(script.textContent || '[]', []);
}

function normalizeItems(items) {
    if (!Array.isArray(items)) return [];
    return items
        .map((it) => ({
            slug: String(it?.slug || ''),
            title: String(it?.title || ''),
            time: it?.time ? String(it.time) : '',
        }))
        .filter((it) => it.slug && it.title);
}

function computeTrackWidth(track) {
    // Force layout measurement after DOM updates.
    return track.scrollWidth || 0;
}

function mountBreakingTicker(container) {
    if (!container || container.__shTickerMounted) return;
    container.__shTickerMounted = true;

    const enabled = container.dataset.enabled === '1';
    if (!enabled) return;

    const engine = container.dataset.engine || 'js';
    if (engine !== 'js') return;

    const newsBaseUrl = container.dataset.newsBaseUrl || '/news';
    const apiUrl = container.dataset.apiUrl || '';

    const dividerEnabled = container.dataset.dividerEnabled === '1';
    const dividerLogoUrl = container.dataset.dividerLogoUrl || '';

    const pauseOnHover = container.dataset.pauseOnHover === '1';
    const direction = container.dataset.direction === 'right' ? 'right' : 'left';

    const speed = clamp(parseInt(container.dataset.speedPxPerSec || '90', 10) || 90, 20, 600);
    const gapPx = clamp(parseInt(container.dataset.gapPx || '20', 10) || 20, 0, 80);

    const pollEnabled = container.dataset.pollEnabled === '1';
    const pollIntervalSeconds = clamp(parseInt(container.dataset.pollIntervalSeconds || '45', 10) || 45, 15, 300);

    const track = container.querySelector('[data-breaking-ticker-track]');
    const viewport = container.querySelector('[data-breaking-ticker-viewport]');
    if (!track || !viewport) return;

    let items = normalizeItems(getItemsFromScript(container));
    let running = false;
    let rafId = null;
    let lastTs = 0;
    let offset = 0;
    let isHovered = false;
    let baseWidth = 0;

    const config = { newsBaseUrl };

    const render = () => {
        track.innerHTML = '';

        if (items.length === 0) {
            container.classList.add('sh-breaking-empty');
            return;
        }

        container.classList.remove('sh-breaking-empty');

        track.style.gap = `${gapPx}px`;

        for (let i = 0; i < items.length; i++) {
            track.appendChild(buildItemNode(items[i], config));
            if (dividerEnabled && dividerLogoUrl) {
                track.appendChild(buildDividerNode(dividerLogoUrl));
            }
        }

        // Stable marquee strategy:
        // - Measure one full sequence width (baseWidth).
        // - Duplicate the sequence enough times so total width > viewport + baseWidth.
        // - Animate a single translateX offset and wrap when offset crosses baseWidth.
        baseWidth = computeTrackWidth(track);

        const maxDupes = 10;
        let dupes = 0;
        const sequenceNodes = Array.from(track.childNodes);

        while (computeTrackWidth(track) < viewport.clientWidth + baseWidth && dupes < maxDupes) {
            for (const node of sequenceNodes) {
                track.appendChild(node.cloneNode(true));
            }
            dupes++;
        }

        // For right-to-left (direction="right"), start at -baseWidth for a smooth wrap.
        if (direction === 'right') {
            offset = -baseWidth;
        } else {
            offset = 0;
        }

        applyTransform();
    };

    const applyTransform = () => {
        track.style.transform = `translate3d(${offset}px, 0, 0)`;
    };

    const step = (ts) => {
        if (!running) return;

        if (!lastTs) lastTs = ts;
        const dt = (ts - lastTs) / 1000;
        lastTs = ts;

        const paused = pauseOnHover && isHovered;
        if (!paused && items.length > 0) {
            const delta = speed * dt;
            offset += direction === 'right' ? delta : -delta;

            // Wrap cleanly at exactly one sequence width.
            if (baseWidth > 0) {
                if (direction === 'left' && offset <= -baseWidth) {
                    offset += baseWidth;
                } else if (direction === 'right' && offset >= 0) {
                    offset -= baseWidth;
                }
            }
        }

        applyTransform();
        rafId = window.requestAnimationFrame(step);
    };

    const start = () => {
        if (running) return;
        running = true;
        lastTs = 0;
        if (rafId) window.cancelAnimationFrame(rafId);
        rafId = window.requestAnimationFrame(step);
    };

    const stop = () => {
        running = false;
        if (rafId) window.cancelAnimationFrame(rafId);
        rafId = null;
    };

    const reset = () => {
        offset = direction === 'right' ? -baseWidth : 0;
        applyTransform();
    };

    const setItems = (nextItems) => {
        items = normalizeItems(nextItems);
        render();
    };

    const refresh = async () => {
        if (!apiUrl) return;
        try {
            const response = await fetch(apiUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const payload = await response.json();
            if (!payload || payload.ok !== true) return;
            const next = normalizeItems(payload.items || []);

            const oldKey = items.map((i) => i.slug).join('|');
            const newKey = next.map((i) => i.slug).join('|');
            if (oldKey !== newKey) {
                setItems(next);
            }
        } catch {
            // ignore
        }
    };

    render();
    start();

    if (pauseOnHover) {
        container.addEventListener('mouseenter', () => {
            isHovered = true;
        });
        container.addEventListener('mouseleave', () => {
            isHovered = false;
        });
    }

    // Re-render on resize (prevents gaps / no-move on narrow screens).
    let resizeTimer = null;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(() => {
            render();
        }, 200);
    });

    if (pollEnabled) {
        // First refresh immediately (no delay).
        refresh();
        window.setInterval(refresh, pollIntervalSeconds * 1000);
    }

    // Expose a small API for debugging.
    container.__shTicker = { start, stop, reset, setItems, refresh };
}

export function initBreakingTickers() {
    document.querySelectorAll('[data-breaking-ticker]').forEach((el) => mountBreakingTicker(el));
}

// Auto-init on normal page loads.
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', () => initBreakingTickers());
}
