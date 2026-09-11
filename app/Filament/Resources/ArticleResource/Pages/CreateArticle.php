<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Services\BreakingNewsLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! empty($data['manual_image'])) {
            $data['image_url'] = Storage::disk('public_uploads')->url($data['manual_image']);
        }
        unset($data['manual_image']);

        if (($data['status'] ?? 'published') === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (Schema::hasColumn('articles', 'is_breaking_locked')) {
            $data['is_breaking_locked'] = true;
        }

        if (Schema::hasColumn('articles', 'show_on_home_locked')) {
            $data['show_on_home_locked'] = true;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(BreakingNewsLimiter::class)->keepLatest();
        Cache::forget('news.breaking.ticker');
        Cache::forget('news.breaking.ticker.v2');
        Cache::forget('news.home.hero');
        Cache::forget('news.home.latest');

        foreach (range(1, 10) as $page) {
            Cache::forget("home.page.data.v1.{$page}");
        }
    }
}
