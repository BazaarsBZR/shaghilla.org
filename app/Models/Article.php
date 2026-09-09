<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Article extends Model
{
    private static ?bool $hasShowOnHomeColumn = null;

    protected $fillable = [
        'feed_source_id',
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'canonical_url',
        'canonical_url_hash',
        'guid',
        'guid_hash',
        'image_url',
        'published_at',
        'imported_at',
        'is_breaking',
        'is_breaking_locked',
        'show_on_home',
        'show_on_home_locked',
        'language',
        'status',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'imported_at' => 'datetime',
        'is_breaking' => 'boolean',
        'is_breaking_locked' => 'boolean',
        'show_on_home' => 'boolean',
        'show_on_home_locked' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Article $article): void {
            if (! filled($article->slug)) {
                $article->slug = self::uniqueSlugFor($article);
            }

            if ($article->canonical_url && ! $article->canonical_url_hash) {
                $article->canonical_url_hash = hash('sha256', $article->canonical_url);
            }

            if (! $article->guid_hash) {
                $basis = $article->guid ?: $article->canonical_url ?: $article->slug ?: $article->title;
                $article->guid_hash = hash('sha256', ($article->feed_source_id ?: 'manual').'|'.$basis);
            }

            $article->imported_at ??= now();
            $article->published_at ??= now();
            $article->language ??= 'ar';
            $article->status ??= 'published';

            if (self::hasShowOnHomeColumn() && $article->getAttribute('show_on_home') === null) {
                $article->setAttribute('show_on_home', true);
            }
        });
    }

    private static function uniqueSlugFor(Article $article): string
    {
        $base = Str::of((string) $article->title)
            ->lower()
            ->replaceMatches('/[^\pL\pN]+/u', '-')
            ->trim('-')
            ->limit(190, '')
            ->toString();

        if ($base === '') {
            $base = 'article-'.now()->format('Ymd-His');
        }

        $slug = $base;
        $suffix = 2;

        while (self::query()
            ->where('slug', $slug)
            ->when(
                $article->exists,
                fn ($query) => $query->where($article->getKeyName(), '!=', $article->getKey()),
            )
            ->exists()) {
            $slug = Str::limit($base, 180, '').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public static function hasShowOnHomeColumn(): bool
    {
        if (self::$hasShowOnHomeColumn !== null) {
            return self::$hasShowOnHomeColumn;
        }

        try {
            self::$hasShowOnHomeColumn = Schema::hasColumn('articles', 'show_on_home');
        } catch (\Throwable) {
            self::$hasShowOnHomeColumn = false;
        }

        return self::$hasShowOnHomeColumn;
    }

    public function feedSource(): BelongsTo
    {
        return $this->belongsTo(FeedSource::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
