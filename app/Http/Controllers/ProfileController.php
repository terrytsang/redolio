<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** The signed-up details a user can change themselves: name, company, email, password. */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user(), 'plan' => $request->user()->currentPlan()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update([
            'name' => trim(strip_tags($data['name'])),
            'company_name' => isset($data['company_name']) ? (trim(strip_tags($data['company_name'])) ?: null) : null,
            'email' => $data['email'],
        ]);

        return redirect()->route('profile.edit')->with('status', 'Your details were saved.');
    }

    public function password(Request $request): RedirectResponse
    {
        $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);

        $request->user()->update(['password' => $request->input('password')]); // hashed by the model cast
        $request->session()->regenerate(); // a changed password should not keep the old session id

        return redirect()->route('profile.edit')->with('status', 'Your password was changed.');
    }
}
