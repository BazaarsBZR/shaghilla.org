<?php

namespace App\Filament\Widgets;

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
            Stat::make('Published Today', number_format($publishedToday))
                ->description('Articles published since midnight')
                ->descriptionIcon('heroicon-m-newspaper')
                ->color('success'),

            Stat::make('Breaking (24h)', number_format($breakingLastDay))
                ->description('Breaking articles in the last 24 hours')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('danger'),

            Stat::make('Active Feeds', number_format($activeFeeds))
                ->description('Feed sources currently enabled')
                ->descriptionIcon('heroicon-m-rss')
                ->color('primary'),

            Stat::make('Pending Memberships', number_format($pendingMemberships))
                ->description('Applications awaiting review')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('warning'),

            Stat::make('Service Requests (7d)', number_format($newServiceRequests))
                ->description('Recent contact submissions')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('info'),
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
