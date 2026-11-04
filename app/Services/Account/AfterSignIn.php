<?php

namespace App\Services\Account;

use App\Models\AnalysisRun;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Where a person goes right after signing up or in — one place, so every route in (email, Google, login, register) ends the
 * same way: a website they typed on the homepage is scanned at once; a scan made before signing up is claimed; otherwise home.
 */
class AfterSignIn
{
    public function __construct(private readonly AnalysisStarter $starter) {}

    public function redirect(Request $request, User $user, bool $isNew = false): RedirectResponse
    {
        if ($remix = $request->input('remix_direction')) {
            $request->session()->put('remix_direction', $remix);
        }

        if ($pending = $request->session()->pull('pending_analysis')) {
            try {
                $run = $this->starter->start($pending['submitted'], $pending['normalized'], $user);

                return redirect()->route('analyses.show', $run->public_id)->with('status', $isNew ? 'Welcome! We’re analysing your website now.' : 'Welcome back — analysing your website now.');
            } catch (ValidationException $e) {
                return redirect()->route('dashboard')->with('status', collect($e->errors())->flatten()->first());
            }
        }

        if (($publicId = $request->input('claim_run')) && ($run = AnalysisRun::where('public_id', $publicId)->whereNull('user_id')->first())) {
            $run->update(['user_id' => $user->id]);

            return redirect()->intended(route('analyses.show', $run->public_id))->with('status', 'Your REDO is saved to your account.');
        }

        return redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('dashboard'));
    }
}
