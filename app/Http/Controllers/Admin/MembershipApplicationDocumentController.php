<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipApplication;
use Filament\Facades\Filament;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class MembershipApplicationDocumentController extends Controller
{
    public function __invoke(MembershipApplication $membershipApplication): Response
    {
        $user = auth()->user();
        abort_unless($user, 403);

        try {
            $panel = Filament::getPanel('admin');
        } catch (\Throwable) {
            abort(403);
        }

        abort_unless(method_exists($user, 'canAccessPanel') && $user->canAccessPanel($panel), 403);

        $path = $membershipApplication->id_document_path;
        abort_unless(is_string($path) && $path !== '', 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }
}
