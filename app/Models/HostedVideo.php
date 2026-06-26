<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HostedVideo extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'video_path',
        'poster_path',
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
        static::saving(function (HostedVideo $video): void {
            $video->slug = trim((string) $video->slug);
            if ($video->slug === '') {
                $video->slug = Str::slug((string) $video->title);
            }

            $video->slug = $video->uniqueSlug($video->slug);
        });

        $flush = static function (): void {
            Cache::forget('home.hosted_videos');
        };

        static::saved($flush);
        static::deleted($flush);
    }

    public function videoUrl(): string
    {
        return Storage::disk('public_uploads')->url($this->video_path);
    }

    public function posterUrl(): ?string
    {
        if (! $this->poster_path) {
            return null;
        }

        return Storage::disk('public_uploads')->url($this->poster_path);
    }

    private function uniqueSlug(string $slug): string
    {
        $slug = trim($slug);
        $slug = $slug !== '' ? $slug : Str::slug((string) $this->title);
        $slug = $slug !== '' ? $slug : Str::random(8);

        $base = $slug;
        $i = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($this->exists, fn ($q) => $q->whereKeyNot($this->getKey()))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}

