<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SiteSetting extends Model
{
    private static ?bool $settingsTableExists = null;

    /**
     * In-request memoized settings (populated from Cache / DB once).
     *
     * @var array<string, string|null>
     */
    private static array $memo = [];

    private static bool $memoLoaded = false;

    private const CACHE_KEY_ALL = 'site_settings.all.v1';

    protected $fillable = [
        'key',
        'value',
    ];

    private static function hasSettingsTable(): bool
    {
        if (self::$settingsTableExists !== null) {
            return self::$settingsTableExists;
        }

        try {
            self::$settingsTableExists = Schema::hasTable('site_settings');
        } catch (\Throwable) {
            self::$settingsTableExists = false;
        }

        return self::$settingsTableExists;
    }

    private static function loadMemo(): void
    {
        if (self::$memoLoaded) {
            return;
        }

        self::$memoLoaded = true;

        try {
            if (! self::hasSettingsTable()) {
                return;
            }

            $rows = Cache::rememberForever(self::CACHE_KEY_ALL, function (): array {
                return static::query()
                    ->pluck('value', 'key')
                    ->map(fn ($value) => $value !== null ? (string) $value : null)
                    ->all();
            });

            if (is_array($rows)) {
                /** @var array<string, string|null> $rows */
                self::$memo = $rows;
            }
        } catch (\Throwable) {
            // Best-effort: fall back to per-key DB lookup in getValue().
            // Keep memoLoaded=true so we don't repeatedly hit Cache on the same request.
        }
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        self::loadMemo();

        if (self::$memoLoaded && array_key_exists($key, self::$memo)) {
            $value = self::$memo[$key];
            return $value !== null ? $value : $default;
        }

        try {
            if (! self::hasSettingsTable()) {
                return $default;
            }

            $value = static::query()->where('key', $key)->value('value');
        } catch (\Throwable) {
            return $default;
        }

        $value = $value !== null ? (string) $value : null;
        self::$memo[$key] = $value;

        return $value !== null ? $value : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key);

        if ($value === null) {
            return $default;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = static::getValue($key);

        if ($value === null) {
            return $default;
        }

        $value = trim($value);
        if ($value === '' || ! preg_match('/^-?\d+$/', $value)) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setValues(array $values): void
    {
        self::$settingsTableExists = true;

        foreach ($values as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            if (is_int($value) || is_float($value)) {
                $value = (string) $value;
            }

            static::updateOrCreate(
                ['key' => (string) $key],
                ['value' => $value !== null ? (string) $value : null],
            );

            self::$memo[(string) $key] = $value !== null ? (string) $value : null;
        }

        self::$memoLoaded = true;

        try {
            Cache::forget(self::CACHE_KEY_ALL);
        } catch (\Throwable) {
            // ignore
        }
    }
}
