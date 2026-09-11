<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PublicCacheWarmController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) env('CRON_SECRET');
        $provided = (string) $request->bearerToken();

        if ($secret === '' || $provided === '' || ! hash_equals($secret, $provided)) {
            abort(401);
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        $responses = Http::pool(fn (Pool $pool): array => [
            'home' => $pool->as('home')->timeout(50)->get($baseUrl.'/'),
            'public_money' => $pool->as('public_money')->timeout(50)->get($baseUrl.'/public-money'),
            'government_tenders' => $pool->as('government_tenders')->timeout(50)->get($baseUrl.'/government-tenders'),
            'financial_status' => $pool->as('financial_status')->timeout(50)->get($baseUrl.'/financial-status'),
        ]);

        $status = collect($responses)->map(
            fn ($response): int => $response->status(),
        )->all();

        return response()->json([
            'ok' => collect($status)->every(fn (int $code): bool => $code >= 200 && $code < 400),
            'pages' => $status,
        ])->header('Cache-Control', 'no-store');
    }
}
