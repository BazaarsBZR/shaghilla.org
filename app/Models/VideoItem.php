<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class VideoItem extends Model
{
    public const TYPE_EPISODE = 'episode';
    public const TYPE_REPORT = 'report';
    public const TYPE_HOME = 'home';

    protected $fillable = [
        'type',
        'title',
        'youtube_url',
        'thumbnail_url',
        'published_at',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        $flush = static function (): void {
            Cache::forget('home.videos');
            Cache::forget('live.episodes');
            Cache::forget('live.reports');
        };

        static::saved($flush);
        static::deleted($flush);
    }
}
