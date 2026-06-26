<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MaintenanceTools;
use App\Filament\Pages\RssAutomation;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\FeedSourceResource;
use App\Filament\Resources\MembershipApplicationResource;
use App\Filament\Resources\VideoItemResource;
use Filament\Widgets\Widget;

class NewsroomQuickActions extends Widget
{
    protected static string $view = 'filament.widgets.newsroom-quick-actions';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function getActions(): array
    {
        return [
            [
                'label' => 'Create Article',
                'description' => 'Publish a manual or corrected story.',
                'icon' => 'heroicon-o-document-plus',
                'color' => 'primary',
                'url' => ArticleResource::getUrl('create'),
            ],
            [
                'label' => 'Create Video Item',
                'description' => 'Add episode/report cards for the live page.',
                'icon' => 'heroicon-o-video-camera',
                'color' => 'success',
                'url' => VideoItemResource::getUrl('create'),
            ],
            [
                'label' => 'Manage Feed Sources',
                'description' => 'Enable, disable, or edit RSS providers.',
                'icon' => 'heroicon-o-rss',
                'color' => 'info',
                'url' => FeedSourceResource::getUrl('index'),
            ],
            [
                'label' => 'Review Memberships',
                'description' => 'Process pending membership applications.',
                'icon' => 'heroicon-o-user-group',
                'color' => 'warning',
                'url' => MembershipApplicationResource::getUrl('index'),
            ],
            [
                'label' => 'Open Service Requests',
                'description' => 'View and respond to new contact messages.',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'color' => 'gray',
                'url' => ContactMessageResource::getUrl('index'),
            ],
            [
                'label' => 'RSS Automation',
                'description' => 'Run import diagnostics and cron checks.',
                'icon' => 'heroicon-o-cpu-chip',
                'color' => 'danger',
                'url' => RssAutomation::getUrl(),
            ],
            [
                'label' => 'Maintenance Tools',
                'description' => 'Run migrations or clear stale caches safely.',
                'icon' => 'heroicon-o-wrench-screwdriver',
                'color' => 'gray',
                'url' => MaintenanceTools::getUrl(),
            ],
        ];
    }
}
