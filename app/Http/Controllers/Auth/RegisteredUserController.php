<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Account\AfterSignIn;
use App\Services\Account\NewAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;

/** Sign up is deliberately two fields: email and password (or one click with Google). Everything else lives in Profile. */
class RegisteredUserController extends Controller
{
    public function create(Request $request)
    {
        $pending = $request->session()->get('pending_analysis');

        return view('auth.register', [
            'claimRun' => $request->query('claim'),
            'remixDirection' => $request->query('remix'),
            'pendingHost' => $pending ? parse_url($pending['normalized'], PHP_URL_HOST) : null,
        ]);
    }

    public function store(Request $request, NewAccount $accounts, AfterSignIn $after): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Rules\Password::min(8)],
        ], ['email.unique' => 'That email already has an account — sign in instead.']);

        $user = $accounts->create($validated['email'], $validated['password']);
        Auth::login($user);
        $request->session()->regenerate();

        return $after->redirect($request, $user, isNew: true);
    }
}
