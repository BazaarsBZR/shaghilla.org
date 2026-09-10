<x-filament-widgets::widget>
    <section class="sh-newsroom-welcome">
        <div class="sh-newsroom-welcome__copy">
            <span class="sh-newsroom-kicker">Shaghilla newsroom</span>
            <h2>Welcome, {{ auth()->user()?->name ?: 'team' }}</h2>
            <p>Everything needed for today's website work is gathered here. Start with a story, review what needs attention, or check the live site.</p>

            <div class="sh-newsroom-welcome__actions">
                <a href="{{ \App\Filament\Resources\ArticleResource::getUrl('create') }}" class="sh-newsroom-button sh-newsroom-button--primary">
                    <x-filament::icon icon="heroicon-o-plus" />
                    New story
                </a>
                <a href="{{ route('home') }}" target="_blank" rel="noopener noreferrer" class="sh-newsroom-button sh-newsroom-button--secondary">
                    <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" />
                    View live website
                </a>
            </div>
        </div>

        <div class="sh-newsroom-status" aria-label="Website status">
            <span class="sh-newsroom-status__dot"></span>
            <div>
                <strong>Website is online</strong>
                <span>Changes appear after you publish</span>
            </div>
        </div>
    </section>

    <x-filament::section
        heading="What would you like to do?"
        description="Choose a task below. Technical tools stay out of the way of everyday work."
    >
        <div class="sh-newsroom-action-grid">
            @foreach ($this->getActions() as $action)
                <a href="{{ $action['url'] }}" class="sh-newsroom-action-card">
                    <span class="sh-newsroom-action-card__icon">
                        <x-filament::icon :icon="$action['icon']" class="h-6 w-6" />
                    </span>

                    <div>
                        <h3>{{ $action['label'] }}</h3>
                        <p>{{ $action['description'] }}</p>
                    </div>

                    <x-filament::icon icon="heroicon-o-chevron-right" class="sh-newsroom-action-card__arrow" />
                </a>
            @endforeach
        </div>
    </x-filament::section>

    <section class="sh-newsroom-workflow" aria-label="Publishing workflow">
        <div class="sh-newsroom-workflow__title">
            <span>Simple publishing flow</span>
            <strong>Draft, check, publish</strong>
        </div>
        <ol>
            <li><b>1</b><span><strong>Write</strong> Add the headline, story, and media.</span></li>
            <li><b>2</b><span><strong>Check</strong> Preview the details and placement.</span></li>
            <li><b>3</b><span><strong>Publish</strong> Make it visible when it is ready.</span></li>
        </ol>
    </section>
</x-filament-widgets::widget>
