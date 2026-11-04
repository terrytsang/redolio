<?php

namespace App\Services\Account;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;

class NewAccount
{
    /** Create a user on the Free plan. Name defaults to something friendly from the email — it can be changed in Profile. */
    public function create(string $email, ?string $password, ?string $name = null, ?string $googleId = null): User
    {
        $user = User::create([
            'name' => $name ?: Str::headline(Str::before($email, '@')),
            'email' => $email, 'password' => $password ?? Str::random(40), 'role' => 'user', 'google_id' => $googleId,
        ]);
        if ($googleId) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }
        if ($free = Plan::where('plan_key', 'free')->first()) {
            Subscription::create(['user_id' => $user->id, 'plan_id' => $free->id, 'status' => 'active', 'started_at' => now()]);
        }
        event(new Registered($user));

        return $user;
    }
}
