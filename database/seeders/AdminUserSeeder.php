<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first admin so the admin portal (/admin) can be opened on a fresh install.
 *
 *  - ADMIN_EMAIL / ADMIN_PASSWORD in .env are used when set (re-run the seeder after changing them to rotate the password).
 *  - Otherwise an admin is created ONCE with the email "admin@example.com" and a random password shown in the console a single time —
 *    there is no default password in the source code. If an admin already exists nothing is changed.
 * The password is hashed here; it is never stored or compared in plain text.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('redo.admin_email');
        $password = config('redo.admin_password');

        if ($email && $password) {
            User::updateOrCreate(['email' => $email], ['name' => 'Admin', 'role' => 'admin', 'password' => $password, 'is_active' => true, 'email_verified_at' => now()]);
            $this->command?->info("Admin account ready: {$email}");

            return;
        }

        if (User::where('role', 'admin')->exists()) {
            $this->command?->info('An admin account already exists — left unchanged.');

            return;
        }

        $email = $email ?: 'admin@example.com';
        $password = Str::password(20, symbols: false);
        User::updateOrCreate(['email' => $email], ['name' => 'Admin', 'role' => 'admin', 'password' => $password, 'is_active' => true, 'email_verified_at' => now()]);

        $this->command?->warn('');
        $this->command?->warn('  First admin created — save this password, it is shown only once:');
        $this->command?->line("    Email:    {$email}");
        $this->command?->line("    Password: {$password}");
        $this->command?->line('  Sign in at /login, then open Admin > Settings > Meta Model API and add your API key.');
        $this->command?->warn('');
    }
}
