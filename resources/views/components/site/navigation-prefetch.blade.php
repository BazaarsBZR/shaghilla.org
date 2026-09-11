@once
    <script>
        (() => {
            if (navigator.connection?.saveData || /(^|-)2g$/.test(navigator.connection?.effectiveType ?? '')) return;

            const pageUrls = @json([
                route('home'),
                route('public-money.index'),
                route('financial-status.index'),
                route('government-tenders.index'),
            ]);
            const prefetched = new Set([window.location.href.split('#')[0]]);

            const prefetch = (url) => {
                if (!url) return;

                const resolved = new URL(url, window.location.href);
                resolved.hash = '';
                if (resolved.origin !== window.location.origin || prefetched.has(resolved.href)) return;

                prefetched.add(resolved.href);
                fetch(resolved.href, {
                    credentials: 'same-origin',
                    cache: 'force-cache',
                    priority: 'low',
                }).catch(() => prefetched.delete(resolved.href));
            };

            const linkFromEvent = (event) => event.target.closest?.('[data-prefetch-page]');
            document.addEventListener('pointerover', (event) => prefetch(linkFromEvent(event)?.href), { passive: true });
            document.addEventListener('focusin', (event) => prefetch(linkFromEvent(event)?.href));
            document.addEventListener('touchstart', (event) => prefetch(linkFromEvent(event)?.href), { passive: true });

            const warmMainPages = () => pageUrls.forEach((url, index) => {
                window.setTimeout(() => prefetch(url), index * 180);
            });

            if ('requestIdleCallback' in window) {
                window.requestIdleCallback(warmMainPages, { timeout: 1800 });
            } else {
                window.addEventListener('load', () => window.setTimeout(warmMainPages, 650), { once: true });
            }
        })();
    </script>
@endonce
