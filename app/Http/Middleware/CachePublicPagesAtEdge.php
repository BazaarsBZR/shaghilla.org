<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CachePublicPagesAtEdge
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            ! $request->isMethod('GET')
            || $request->user() !== null
            || ! $this->isCacheablePage($request)
            || $response->getStatusCode() !== 200
            || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')
        ) {
            return $response;
        }

        // Data pages change on scheduled imports, so a short browser cache makes repeat
        // tab visits instant without hiding new records for long. Editorial pages keep
        // a much shorter window because they can update throughout the day.
        $browserMaxAge = $request->is('public-money')
            || $request->is('public-money/*')
            || $request->is('government-tenders')
            || $request->is('government-tenders/*')
            || $request->is('financial-status')
                ? 300
                : 30;

        $response->headers->remove('Set-Cookie');
        $response->headers->set('Cache-Control', "public, max-age={$browserMaxAge}, stale-while-revalidate=60");
        $response->headers->set('CDN-Cache-Control', 'public, s-maxage=300, stale-while-revalidate=86400');
        $response->headers->set('Vercel-CDN-Cache-Control', 'public, s-maxage=300, stale-while-revalidate=86400');

        return $response;
    }

    private function isCacheablePage(Request $request): bool
    {
        return $request->path() === '/'
            || $request->is('news/*')
            || $request->is('live')
            || $request->is('public-money')
            || $request->is('public-money/*')
            || $request->is('government-tenders')
            || $request->is('government-tenders/*')
            || $request->is('financial-status');
    }
}
