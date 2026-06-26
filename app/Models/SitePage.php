<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SitePage extends Model
{
    public const TYPE_ROUTE = 'route';
    public const TYPE_PAGE = 'page';
    public const TYPE_EXTERNAL = 'external';

    protected $fillable = [
        'type',
        'route_name',
        'slug',
        'external_url',
        'title_ar',
        'title_en',
        'content_html_ar',
        'content_html_en',
        'open_in_new_tab',
        'show_in_header',
        'show_in_footer',
        'sort_order',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'open_in_new_tab' => 'boolean',
        'show_in_header' => 'boolean',
        'show_in_footer' => 'boolean',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => self::flushMenuCaches());
        static::deleted(fn () => self::flushMenuCaches());

        static::deleting(function (self $record): void {
            if ($record->is_system) {
                throw ValidationException::withMessages([
                    'is_system' => 'System pages cannot be deleted. Disable or hide them from menus instead.',
                ]);
            }
        });
    }

    public static function flushMenuCaches(): void
    {
        Cache::forget('site.menu.header');
        Cache::forget('site.menu.footer');
    }

    public static function headerMenu()
    {
        try {
            if (! Schema::hasTable('site_pages')) {
                return collect();
            }
        } catch (\Throwable) {
            return collect();
        }

        return Cache::remember(
            'site.menu.header',
            now()->addDay(),
            fn () => self::query()
                ->where('is_active', true)
                ->where('show_in_header', true)
                ->orderBy('sort_order')
                ->get(),
        );
    }

    public static function footerMenu()
    {
        try {
            if (! Schema::hasTable('site_pages')) {
                return collect();
            }
        } catch (\Throwable) {
            return collect();
        }

        return Cache::remember(
            'site.menu.footer',
            now()->addDay(),
            fn () => self::query()
                ->where('is_active', true)
                ->where('show_in_footer', true)
                ->orderBy('sort_order')
                ->get(),
        );
    }

    public function displayTitle(): string
    {
        $locale = app()->getLocale();

        if ($locale === 'en' && ! empty($this->title_en)) {
            return $this->title_en;
        }

        return $this->title_ar;
    }

    public function url(): string
    {
        return match ($this->type) {
            self::TYPE_ROUTE => ($this->route_name && Route::has($this->route_name)) ? route($this->route_name) : '#',
            self::TYPE_EXTERNAL => $this->external_url ?: '#',
            default => ($this->slug && Route::has('pages.show')) ? route('pages.show', ['slug' => $this->slug]) : '#',
        };
    }

    public function target(): ?string
    {
        return $this->open_in_new_tab ? '_blank' : null;
    }

    public function rel(): ?string
    {
        return $this->open_in_new_tab ? 'noopener noreferrer' : null;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $record): void {
            if ($record->type !== self::TYPE_PAGE) {
                $record->slug = null;
                $record->content_html_ar = null;
                $record->content_html_en = null;
            }

            if ($record->type !== self::TYPE_ROUTE) {
                $record->route_name = null;
            }

            if ($record->type !== self::TYPE_EXTERNAL) {
                $record->external_url = null;
            }

            if ($record->type === self::TYPE_PAGE && ! empty($record->slug)) {
                $record->slug = Str::slug($record->slug, '-');
            }
        });
    }
}
