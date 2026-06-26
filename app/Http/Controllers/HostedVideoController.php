<?php

namespace App\Http\Controllers;

use App\Models\HostedVideo;
use Illuminate\View\View;

class HostedVideoController extends Controller
{
    public function show(HostedVideo $hostedVideo): View
    {
        abort_unless($hostedVideo->is_active, 404);

        return view('pages.hosted-video', [
            'video' => $hostedVideo,
        ]);
    }
}

