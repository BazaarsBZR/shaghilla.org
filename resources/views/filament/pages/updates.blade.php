<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section heading="Dashboard updater (no SSH)">
            <div class="space-y-2 text-sm text-gray-700">
                <div>
                    Upload the same zip we normally deploy via cPanel File Manager (created by <code>scripts/build-cpanel-upload.sh</code>).
                    This tool extracts it and switches the site to the new private app folder.
                </div>

                <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-amber-900">
                    <div class="font-semibold">Security warning</div>
                    <div>
                        This feature can deploy code. Keep admin access locked down and set a strong <code>DASHBOARD_UPDATE_TOKEN</code>.
                    </div>
                </div>

                @unless ($updaterEnabled)
                    <div class="rounded-md border border-red-200 bg-red-50 p-3 text-red-900">
                        <div class="font-semibold">Updater disabled</div>
                        <div>
                            Set <code>DASHBOARD_UPDATE_TOKEN</code> in your server <code>.env</code> to enable deployments from this page.
                        </div>
                    </div>
                @endunless

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-md border border-gray-200 p-3">
                        <div class="text-xs font-semibold uppercase text-gray-500">Current app build</div>
                        <div class="mt-1 font-mono text-sm">{{ $currentBuildId ?: '—' }}</div>
                    </div>
                    <div class="rounded-md border border-gray-200 p-3">
                        <div class="text-xs font-semibold uppercase text-gray-500">.app-root override</div>
                        <div class="mt-1 break-all font-mono text-sm">{{ $appRootOverride ?: '—' }}</div>
                    </div>
                    <div class="rounded-md border border-gray-200 p-3">
                        <div class="text-xs font-semibold uppercase text-gray-500">Last dashboard update</div>
                        <div class="mt-1 font-mono text-sm">{{ $lastUpdateAt ?: '—' }}</div>
                    </div>
                </div>

                <div class="text-gray-600">
                    @if ($updaterEnabled)
                        Use the “Deploy update zip” button above to upload and apply an update.
                    @else
                        Once enabled, this page will show a “Deploy update zip” button in the header.
                    @endif
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
