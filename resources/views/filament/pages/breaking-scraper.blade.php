<x-filament-panels::page>
    <div class="max-w-4xl space-y-6" wire:poll.15s="refreshStatus">
        {{ $this->form }}
    </div>
</x-filament-panels::page>
