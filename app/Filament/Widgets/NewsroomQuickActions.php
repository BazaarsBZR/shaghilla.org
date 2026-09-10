<?php

namespace App\Filament\Widgets;

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
                'label' => 'Write a new story',
                'description' => 'Start a draft, add a photo or video, then publish when it is ready.',
                'icon' => 'heroicon-o-document-plus',
                'color' => 'primary',
                'url' => ArticleResource::getUrl('create'),
            ],
            [
                'label' => 'Manage published stories',
                'description' => 'Find, correct, publish, or remove a story from the breaking bar.',
                'icon' => 'heroicon-o-newspaper',
                'color' => 'gray',
                'url' => ArticleResource::getUrl('index'),
            ],
            [
                'label' => 'Add a video',
                'description' => 'Add a broadcast, report, or homepage video for visitors to watch.',
                'icon' => 'heroicon-o-video-camera',
                'color' => 'success',
                'url' => VideoItemResource::getUrl('create'),
            ],
            [
                'label' => 'Read visitor messages',
                'description' => 'Open service requests and messages sent through the website.',
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'color' => 'info',
                'url' => ContactMessageResource::getUrl('index'),
            ],
            [
                'label' => 'Review memberships',
                'description' => 'Check new applications and update their progress.',
                'icon' => 'heroicon-o-user-group',
                'color' => 'warning',
                'url' => MembershipApplicationResource::getUrl('index'),
            ],
            [
                'label' => 'Manage news sources',
                'description' => 'See which automatic news sources are active or paused.',
                'icon' => 'heroicon-o-rss',
                'color' => 'gray',
                'url' => FeedSourceResource::getUrl('index'),
            ],
        ];
    }
}
