<x-filament-widgets::widget>
    <x-filament::section
        heading="Newsroom Shortcuts"
        description="Common admin actions for editorial, membership, and automation tasks."
    >
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->getActions() as $action)
                <a
                    href="{{ $action['url'] }}"
                    class="group block rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-500 hover:shadow-sm dark:border-white/10 dark:bg-gray-900"
                >
                    <div class="flex items-start gap-3">
                        <x-filament::icon
                            :icon="$action['icon']"
                            class="h-5 w-5 text-primary-500"
                        />

                        <div class="space-y-1">
                            <h3 class="text-sm font-semibold text-gray-900 group-hover:text-primary-600 dark:text-gray-100">
                                {{ $action['label'] }}
                            </h3>
                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                {{ $action['description'] }}
                            </p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
