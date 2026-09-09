<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AdminBlobUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
        ]);

        $token = (string) config('vercel.blob_token');
        abort_if($token === '', 503, 'Image storage is not configured.');

        $image = $validated['image'];
        $contentType = $image->getMimeType() ?: 'application/octet-stream';
        $extension = strtolower($image->guessExtension() ?: 'jpg');
        $pathname = 'news/manual/'.Str::uuid().'.'.$extension;

        $response = Http::withToken($token)
            ->withHeaders([
                'x-api-version' => '7',
                'x-content-type' => $contentType,
                'x-add-random-suffix' => '1',
                'x-cache-control-max-age' => '31536000',
            ])
            ->withBody($image->get(), $contentType)
            ->put('https://blob.vercel-storage.com/'.$pathname)
            ->throw();

        return response()->json([
            'url' => $response->json('url'),
        ]);
    }
}
