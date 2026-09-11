<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AdminRecoveryController extends Controller
{
    public function show(string $token): View
    {
        $this->authorizeToken($token);

        return view('admin.recovery', [
            'token' => $token,
            'email' => $this->adminEmail(),
        ]);
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $this->authorizeToken($token);

        $key = 'admin-recovery:'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 3), 429);
        RateLimiter::hit($key, 300);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::query()->updateOrCreate(
            ['email' => $this->adminEmail()],
            [
                'name' => 'Admin',
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ],
        );

        RateLimiter::clear($key);

        return redirect('/admin/login')->with('status', 'Admin access restored. You can sign in now.');
    }

    private function authorizeToken(string $token): void
    {
        $expected = (string) env('ADMIN_RECOVERY_TOKEN', '');
        abort_if($expected === '' || ! hash_equals($expected, $token), 404);
    }

    private function adminEmail(): string
    {
        return (string) env('ADMIN_EMAIL', 'admin@shaghilla.org');
    }
}
