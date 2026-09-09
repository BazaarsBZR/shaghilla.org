<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Services\BreakingNewsLimiter;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        if (! empty($data['manual_image'])) {
            $data['image_url'] = Storage::disk('public_uploads')->url($data['manual_image']);
        }
        unset($data['manual_image']);

        if (($data['status'] ?? 'published') === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if (Schema::hasColumn('articles', 'is_breaking_locked') && array_key_exists('is_breaking', $data)) {
            $incoming = filter_var($data['is_breaking'], FILTER_VALIDATE_BOOL);
            if ((bool) $record->is_breaking !== (bool) $incoming) {
                $data['is_breaking_locked'] = true;
            }
        }

        if (Schema::hasColumn('articles', 'show_on_home_locked') && array_key_exists('show_on_home', $data)) {
            $incoming = filter_var($data['show_on_home'], FILTER_VALIDATE_BOOL);
            $current = Schema::hasColumn('articles', 'show_on_home')
                ? (bool) ($record->show_on_home ?? true)
                : true;

            if ($current !== (bool) $incoming) {
                $data['show_on_home_locked'] = true;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        app(BreakingNewsLimiter::class)->keepLatest();
        Cache::forget('news.breaking.ticker');
        Cache::forget('news.home.hero');
        Cache::forget('news.home.latest');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
