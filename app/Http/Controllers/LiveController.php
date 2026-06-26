<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Models\VideoItem;
use App\Support\YouTube;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class LiveController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $liveYoutubeUrl = SiteSetting::getValue('live_youtube_url', '');
        $liveYoutubeId = YouTube::extractId($liveYoutubeUrl);

        $playlistLimit = SiteSetting::getInt(
            'live_youtube_playlist_limit',
            SiteSetting::getInt('home_video_grid_limit', 12),
        );
        $playlistLimit = max(1, min(12, $playlistLimit));

        $playlistVideos = Cache::remember(
            'home.videos',
            now()->addMinutes(10),
            fn () => VideoItem::query()
                ->where('is_active', true)
                ->where('type', VideoItem::TYPE_HOME)
                ->orderBy('sort_order')
                ->orderByDesc('published_at')
                ->limit(30)
                ->get(),
        )->take($playlistLimit);

        $requestedId = YouTube::extractId((string) $request->query('v', ''));
        $selectedYoutubeId = $requestedId ?: $liveYoutubeId;
        if (! $selectedYoutubeId && $playlistVideos->isNotEmpty()) {
            $selectedYoutubeId = YouTube::extractId((string) $playlistVideos->first()->youtube_url);
        }

        return view('pages.live', [
            'liveYoutubeUrl' => $liveYoutubeUrl,
            'liveYoutubeId' => $liveYoutubeId,
            'playlistVideos' => $playlistVideos,
            'selectedYoutubeId' => $selectedYoutubeId,
        ]);
    }
}
