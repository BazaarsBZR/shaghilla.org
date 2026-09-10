<?php

namespace App\Services\PublicMoney;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OfficialSourceClient
{
    private const ALLOWED_HOSTS = ['ppa.gov.lb', 'www.ppa.gov.lb', 'finance.gov.lb', 'www.finance.gov.lb'];

    /** @var array<string, true> */
    private array $validatedHosts = [];

    /** @return array{body: string, url: string, mime: string} */
    public function get(string $url): array
    {
        for ($redirects = 0; $redirects < 5; $redirects++) {
            $this->assertSafeUrl($url);

            $response = Http::withHeaders($this->headers())
                ->connectTimeout(8)
            ->timeout(60)
                ->withoutRedirecting()
                ->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');
                if (! $location) {
                    throw new RuntimeException('The official source returned a redirect without a location.');
                }

                $url = $this->resolveUrl($url, $location);
                continue;
            }

            $response->throw();
            $this->assertUsableResponse($response);

            return [
                'body' => $response->body(),
                'url' => $url,
                'mime' => (string) $response->header('Content-Type'),
            ];
        }

        throw new RuntimeException('The official source redirected too many times.');
    }

    /**
     * Fetch independent official pages concurrently while keeping failures isolated.
     *
     * @param  array<int, string>  $urls
     * @return array<string, array{body?: string, url: string, mime?: string, error?: string}>
     */
    public function getMany(array $urls, int $batchSize = 8): array
    {
        $results = [];

        foreach (array_chunk(array_values(array_unique($urls)), max(1, $batchSize)) as $chunk) {
            $keyedUrls = [];
            foreach ($chunk as $url) {
                try {
                    $this->assertSafeUrl($url);
                    $keyedUrls['url_'.sha1($url)] = $url;
                } catch (Throwable $exception) {
                    $results[$url] = ['url' => $url, 'error' => $exception->getMessage()];
                }
            }

            if ($keyedUrls === []) {
                continue;
            }

            $responses = Http::pool(function (Pool $pool) use ($keyedUrls): array {
                $requests = [];
                foreach ($keyedUrls as $key => $url) {
                    $requests[] = $pool->as($key)
                        ->withHeaders($this->headers())
                        ->connectTimeout(8)
                        ->timeout(25)
                        ->withoutRedirecting()
                        ->get($url);
                }

                return $requests;
            });

            foreach ($keyedUrls as $key => $url) {
                $response = $responses[$key] ?? null;

                try {
                    if (! $response instanceof Response) {
                        throw new RuntimeException('The official source did not return a response.');
                    }

                    if ($response->redirect()) {
                        $results[$url] = $this->get($url);
                        continue;
                    }

                    $response->throw();
                    $this->assertUsableResponse($response);
                    $results[$url] = [
                        'body' => $response->body(),
                        'url' => $url,
                        'mime' => (string) $response->header('Content-Type'),
                    ];
                } catch (Throwable $exception) {
                    $results[$url] = ['url' => $url, 'error' => $exception->getMessage()];
                }
            }
        }

        return $results;
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'User-Agent' => 'Shaghilla Public Money/1.0 (+https://shaghilla.org)',
            'Accept' => 'text/html,application/xhtml+xml,application/pdf;q=0.9,*/*;q=0.8',
        ];
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Only credential-free HTTPS source URLs are allowed.');
        }

        if (! in_array($host, self::ALLOWED_HOSTS, true)) {
            throw new RuntimeException('The source host is not on the official allowlist.');
        }

        if (app()->environment('testing') || isset($this->validatedHosts[$host])) {
            return;
        }

        $addresses = gethostbynamel($host) ?: [];
        if ($addresses === []) {
            throw new RuntimeException('The official source host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                throw new RuntimeException('The official source resolved to a private or reserved address.');
            }
        }

        $this->validatedHosts[$host] = true;
    }

    private function assertUsableResponse(Response $response): void
    {
        $contentType = strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/pdf')) {
            throw new RuntimeException('The official source returned an unsupported content type.');
        }

        if (strlen($response->body()) > 12 * 1024 * 1024) {
            throw new RuntimeException('The official source response exceeded the 12 MB safety limit.');
        }
    }

    private function resolveUrl(string $baseUrl, string $location): string
    {
        if (str_starts_with($location, 'https://')) {
            return $location;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = rtrim(str_replace('\\', '/', dirname((string) ($parts['path'] ?? '/'))), '/');

        return $origin.($directory === '' ? '' : $directory).'/'.$location;
    }
}
