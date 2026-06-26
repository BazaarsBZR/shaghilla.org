<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SharePlatform extends Model
{
    private static ?bool $tableExists = null;

    private const CACHE_KEY_ACTIVE = 'share_platforms.active.v1';

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'share_url_template',
        'use_native_share',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'use_native_share' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget(self::CACHE_KEY_ACTIVE);
        });

        static::deleted(function (): void {
            Cache::forget(self::CACHE_KEY_ACTIVE);
        });
    }

    private static function hasTable(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        try {
            self::$tableExists = Schema::hasTable('share_platforms');
        } catch (\Throwable) {
            self::$tableExists = false;
        }

        return self::$tableExists;
    }

    /**
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function activeCached(): Collection
    {
        if (! self::hasTable()) {
            return collect();
        }

        return Cache::rememberForever(self::CACHE_KEY_ACTIVE, function (): Collection {
            return static::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Returns a share link for platforms that support URL templates.
     * Use placeholders: {url} and {title}.
     */
    public function buildShareUrl(string $url, string $title): ?string
    {
        $template = trim((string) ($this->share_url_template ?? ''));

        if ($template === '') {
            return null;
        }

        $encodedUrl = rawurlencode($url);
        $encodedTitle = rawurlencode($title);

        return str_replace(
            ['{url}', '{title}'],
            [$encodedUrl, $encodedTitle],
            $template,
        );
    }

    /**
     * Icon picker options (Font Awesome icon names).
     *
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'fa-whatsapp' => 'WhatsApp',
            'fa-x-twitter' => 'X (Twitter)',
            'fa-facebook' => 'Facebook',
            'fa-facebook-f' => 'Facebook (f)',
            'fa-instagram' => 'Instagram',
            'fa-linkedin' => 'LinkedIn',
            'fa-youtube' => 'YouTube',
            'fa-tiktok' => 'TikTok',
            'fa-snapchat' => 'Snapchat',
            'fa-reddit' => 'Reddit',
            'fa-pinterest' => 'Pinterest',
            'fa-skype' => 'Skype',
            'fa-discord' => 'Discord',
        ];
    }
}
