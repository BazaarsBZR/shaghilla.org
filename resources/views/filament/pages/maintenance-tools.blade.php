<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section heading="Cache clearing">
            <div class="space-y-2 text-sm text-gray-700">
                <div>
                    <div class="font-semibold">Last clear</div>
                    <div>{{ $lastCacheClearAt ?: '—' }}</div>
                </div>

                <div>
                    Use the “Clear caches” button after deploying new files to ensure updated Blade/views/config are loaded.
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Scheduler locks">
            <div class="space-y-2 text-sm text-gray-700">
                <div>
                    <div class="font-semibold">Last scheduler lock clear</div>
                    <div>{{ $lastScheduleClearAt ?: '—' }}</div>
                </div>

                <div>
                    If scheduled tasks stop running (for example RSS imports get “stuck” because of a stale overlap lock), use the “Clear scheduler locks” button (top right).
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Database cleanup">
            <div class="space-y-2 text-sm text-gray-700">
                <div>
                    If RSS imports keep “skipping” because the DB already contains the same articles, you can prune old RSS articles here.
                </div>
                <div>
                    Use the “Prune old RSS articles” button (top right). It only deletes RSS-imported articles (manual / curated items are kept).
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
