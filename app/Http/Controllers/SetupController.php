<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (User::where('role', 'admin')->exists()) {
            return redirect()->route('landing');
        }

        $apiKeySet = (bool) PlatformSetting::get('meta_api_key', config('meta_api.api_key'));

        return view('setup.index', [
            'apiKeySet' => $apiKeySet,
            'baseUrl' => PlatformSetting::get('meta_api_base_url', config('meta_api.base_url')),
            'modelId' => PlatformSetting::get('meta_api_model_id', config('meta_api.model_id')),
            'claimRun' => $request->query('run'),
        ]);
    }

    public function saveApiKey(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'meta_api_base_url' => ['required', 'string', 'max:255'],
            'meta_api_key' => ['required', 'string', 'max:255'],
            'meta_api_model_id' => ['required', 'string', 'max:255'],
        ]);

        PlatformSetting::set('meta_api_base_url', $validated['meta_api_base_url']);
        PlatformSetting::set('meta_api_key', $validated['meta_api_key']);
        PlatformSetting::set('meta_api_model_id', $validated['meta_api_model_id']);

        return redirect()->route('setup.index')->with('status', 'API key saved. Try analyzing a website below.');
    }

    public function createAdmin(Request $request): RedirectResponse
    {
        if (User::where('role', 'admin')->exists()) {
            return redirect()->route('landing');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $admin = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'admin',
        ]);

        event(new Registered($admin));

        if ($publicId = $request->input('claim_run')) {
            AnalysisRun::where('public_id', $publicId)->whereNull('user_id')->update(['user_id' => $admin->id]);
        }

        Auth::login($admin);

        return redirect()->route('admin.dashboard')->with('status', 'Setup complete — welcome to your REDOLIO installation.');
    }
}
