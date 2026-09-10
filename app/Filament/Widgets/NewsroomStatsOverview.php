<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\FeedSourceResource;
use App\Filament\Resources\MembershipApplicationResource;
use App\Models\Article;
use App\Models\ContactMessage;
use App\Models\FeedSource;
use App\Models\MembershipApplication;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class NewsroomStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $publishedToday = $this->tableExists('articles')
            ? Article::query()
                ->where('status', 'published')
                ->where('published_at', '>=', now()->startOfDay())
                ->count()
            : 0;

        $breakingLastDay = $this->tableExists('articles')
            ? Article::query()
                ->where('is_breaking', true)
                ->where('published_at', '>=', now()->subDay())
                ->count()
            : 0;

        $activeFeeds = $this->tableExists('feed_sources')
            ? FeedSource::query()->where('is_active', true)->count()
            : 0;

        $pendingMemberships = $this->tableExists('membership_applications')
            ? MembershipApplication::query()->where('status', MembershipApplication::STATUS_PENDING)->count()
            : 0;

        $newServiceRequests = $this->tableExists('contact_messages')
            ? ContactMessage::query()->where('created_at', '>=', now()->subDays(7))->count()
            : 0;

        return [
            Stat::make('Stories live today', number_format($publishedToday))
                ->description('Published since midnight')
                ->descriptionIcon('heroicon-m-newspaper')
                ->color('success')
                ->url(ArticleResource::getUrl('index')),

            Stat::make('Breaking stories', number_format($breakingLastDay))
                ->description('Marked breaking in the last 24 hours')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('danger')
                ->url(ArticleResource::getUrl('index')),

            Stat::make('News sources', number_format($activeFeeds))
                ->description('Automatic sources currently active')
                ->descriptionIcon('heroicon-m-rss')
                ->color('primary')
                ->url(FeedSourceResource::getUrl('index')),

            Stat::make('Memberships to review', number_format($pendingMemberships))
                ->description('Applications waiting for your decision')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('warning')
                ->url(MembershipApplicationResource::getUrl('index')),

            Stat::make('New messages', number_format($newServiceRequests))
                ->description('Received during the last 7 days')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info')
                ->url(ContactMessageResource::getUrl('index')),
        ];
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
