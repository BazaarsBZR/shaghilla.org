<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ArticleScraper
{
    /**
     * @return array{content?:string, excerpt?:string, image_url?:string}
     */
    public function scrape(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return [];
        }

        $response = Http::timeout(25)
            ->withUserAgent('ShaghillaArticleScraper/1.0')
            ->retry(2, 500, function ($exception): bool {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $status = $exception->response?->status();

                    return $status === 429 || ($status !== null && $status >= 500);
                }

                return true;
            }, false)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()}");
        }

        $html = (string) $response->body();
        if ($html === '') {
            return [];
        }

        $imageUrl =
            $this->extractMetaContent($html, 'property', 'og:image')
            ?: $this->extractMetaContent($html, 'name', 'twitter:image');
        $imageUrl = $this->normalizeMaybeRelativeUrl($imageUrl, $url);

        $contentHtml = $this->extractArticleBodyHtml($html);
        $bodyImageUrl = $this->normalizeMaybeRelativeUrl($this->extractFirstImageFromHtml($contentHtml), $url);
        if ($bodyImageUrl && (! $imageUrl || $this->isPlaceholderImageUrl($imageUrl))) {
            $imageUrl = $bodyImageUrl;
        }
        $contentText = $this->htmlToText($contentHtml);

        $result = [];

        if ($contentText !== '') {
            $result['content'] = $contentText;
            $result['excerpt'] = Str::limit(trim(preg_replace('/\s+/u', ' ', $contentText)), 240);
        }

        if ($imageUrl) {
            $result['image_url'] = $imageUrl;
        }

        return $result;
    }

    private function normalizeMaybeRelativeUrl(?string $url, string $baseUrl): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return "{$scheme}:{$url}";
        }

        if (str_starts_with($url, '/')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
            $host = parse_url($baseUrl, PHP_URL_HOST) ?: '';

            if ($host === '') {
                return null;
            }

            return "{$scheme}://{$host}{$url}";
        }

        if (! str_contains($url, '://') && str_starts_with($url, 'www.')) {
            return "https://{$url}";
        }

        return $url;
    }

    private function isPlaceholderImageUrl(string $url): bool
    {
        $url = strtolower(trim($url));

        return $url === ''
            || str_contains($url, 'default-document-thumbnail')
            || str_contains($url, 'default-document-picture')
            || str_contains($url, 'placeholder');
    }

    private function extractFirstImageFromHtml(string $html): ?string
    {
        if ($html === '') {
            return null;
        }

        if (preg_match('/<img[^>]+src=["\\\']([^"\\\']+)["\\\']/i', $html, $matches) !== 1) {
            return null;
        }

        $url = trim($matches[1]);

        return $url !== '' ? $url : null;
    }

    private function extractMetaContent(string $html, string $attrName, string $attrValue): ?string
    {
        $pattern = sprintf(
            '/<meta[^>]+%s=["\\\']%s["\\\'][^>]*content=["\\\']([^"\\\']+)["\\\'][^>]*>/i',
            preg_quote($attrName, '/'),
            preg_quote($attrValue, '/'),
        );

        if (preg_match($pattern, $html, $matches) !== 1) {
            return null;
        }

        $url = html_entity_decode(trim($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $url !== '' ? $url : null;
    }

    private function extractArticleBodyHtml(string $html): string
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $loaded = $dom->loadHTML(
            mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        if (! $loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);

        $node = $xpath->query('//*[@itemprop="articleBody"]')->item(0);
        if (! $node) {
            $node = $xpath->query('//article')->item(0);
        }

        if (! $node) {
            return $html;
        }

        $inner = '';
        foreach ($node->childNodes as $child) {
            $inner .= $dom->saveHTML($child);
        }

        return $inner !== '' ? $inner : $html;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<script\\b[^>]*>.*?<\\/script>/is', '', $html) ?: $html;
        $html = preg_replace('/<style\\b[^>]*>.*?<\\/style>/is', '', $html) ?: $html;

        $html = preg_replace('/<\\s*br\\s*\\/?>/i', "\n", $html) ?: $html;
        $html = preg_replace('/<\\/p\\s*>/i', "\n\n", $html) ?: $html;
        $html = preg_replace('/<\\/div\\s*>/i', "\n", $html) ?: $html;

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \\t]+/u", ' ', $text) ?: $text;
        $text = preg_replace("/\\n{3,}/u", "\n\n", $text) ?: $text;

        return trim($text);
    }
}
