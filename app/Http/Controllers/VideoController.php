<?php

namespace App\Http\Controllers;

use App\Models\VideoItem;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function show(VideoItem $videoItem): View
    {
        abort_unless($videoItem->is_active, 404);

        return view('pages.video', [
            'video' => $videoItem,
        ]);
    }
}

