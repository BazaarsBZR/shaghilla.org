<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', 'admin@shaghilla.org');
        $password = (string) env('ADMIN_PASSWORD', '');

        // No hardcoded default password: require ADMIN_PASSWORD to be set so the
        // admin account is never created with a guessable, committed credential.
        if ($password === '') {
            $this->command?->warn('AdminUserSeeder skipped: set ADMIN_PASSWORD in .env to create/update the admin user.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );
    }
}
