<?php

namespace App\Support;

class YouTube
{
    public static function extractId(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $value) === 1) {
            return $value;
        }

        $parts = parse_url($value);
        if (! $parts) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if ($host === 'youtu.be' || str_ends_with($host, '.youtu.be')) {
            $candidate = trim($path, '/');

            return $candidate !== '' ? $candidate : null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        if (! empty($query['v']) && is_string($query['v'])) {
            return $query['v'];
        }

        if (preg_match('#/(?:embed|live|shorts)/([^/?]+)#', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public static function embedUrl(?string $value): ?string
    {
        $id = static::extractId($value);
        if (! $id) {
            return null;
        }

        return "https://www.youtube.com/embed/{$id}?rel=0";
    }
}

