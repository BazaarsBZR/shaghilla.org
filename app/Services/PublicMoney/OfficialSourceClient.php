<?php

namespace App\Services\PublicMoney;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OfficialSourceClient
{
    private const ALLOWED_HOSTS = ['ppa.gov.lb', 'www.ppa.gov.lb', 'finance.gov.lb', 'www.finance.gov.lb'];

    /** @return array{body: string, url: string, mime: string} */
    public function get(string $url): array
    {
        for ($redirects = 0; $redirects <= 3; $redirects++) {
            $this->assertSafeUrl($url);
            $response = Http::withHeaders([
                'User-Agent' => 'Shaghilla Public Money Monitor/1.0 (+https://shaghilla.org/public-money/sources)',
                'Accept' => 'text/html,application/pdf;q=0.9',
            ])->connectTimeout(8)->timeout(35)->retry(2, 600)->withoutRedirecting()->get($url);

            if ($response->redirect()) {
                $location = $response->header('Location');
                if (! $location) {
                    throw new RuntimeException('Official source returned a redirect without a location.');
                }
                $url = $this->resolveUrl($url, $location);
                continue;
            }

            $response->throw();
            $this->assertAllowedResponse($response);

            return [
                'body' => $response->body(),
                'url' => $url,
                'mime' => strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0])),
            ];
        }

        throw new RuntimeException('Official source exceeded the redirect limit.');
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || ! in_array($host, self::ALLOWED_HOSTS, true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('Blocked non-official or unsafe source URL.');
        }

        if (! app()->environment('testing')) {
            $ips = array_values(array_unique(array_filter([
                gethostbyname($host),
                ...array_map(fn (array $row): string => (string) ($row['ip'] ?? ''), dns_get_record($host, DNS_A) ?: []),
            ])));
            foreach ($ips as $ip) {
                if ($ip === $host || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    throw new RuntimeException('Blocked source resolving to a private or reserved address.');
                }
            }
        }
    }

    private function assertAllowedResponse(Response $response): void
    {
        $mime = strtolower((string) $response->header('Content-Type'));
        if (! str_contains($mime, 'text/html') && ! str_contains($mime, 'application/pdf')) {
            throw new RuntimeException('Official source returned an unsupported content type.');
        }
        if (strlen($response->body()) > 12 * 1024 * 1024) {
            throw new RuntimeException('Official source document exceeded the 12 MB safety limit.');
        }
    }

    private function resolveUrl(string $base, string $location): string
    {
        if (str_starts_with($location, 'https://')) {
            return $location;
        }
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }
        $path = rtrim(dirname((string) ($parts['path'] ?? '/')), '/');
        return $origin.$path.'/'.$location;
    }
}
