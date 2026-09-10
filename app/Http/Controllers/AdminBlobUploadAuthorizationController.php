<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBlobUploadAuthorizationController extends Controller
{
    private const MAX_BYTES = 250 * 1024 * 1024;

    private const ALLOWED_CONTENT_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-m4v',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(self::ALLOWED_CONTENT_TYPES)],
            'size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_BYTES],
        ]);

        $appKey = (string) config('app.key');
        abort_if($appKey === '', 503, 'Upload authorization is not configured.');

        $claims = [
            'user_id' => (int) $request->user()->getAuthIdentifier(),
            'expires' => now()->addMinutes(5)->timestamp,
            'type' => $validated['type'],
            'max_bytes' => min((int) $validated['size'], self::MAX_BYTES),
            'nonce' => bin2hex(random_bytes(16)),
        ];

        $payload = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $payload, $appKey);

        return response()->json([
            'authorization' => $payload.'.'.$signature,
            'expires_at' => $claims['expires'],
        ]);
    }
}
