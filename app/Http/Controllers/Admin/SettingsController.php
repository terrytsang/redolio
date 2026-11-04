<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'baseUrl' => PlatformSetting::get('meta_api_base_url', config('meta_api.base_url')),
            'apiKeySet' => (bool) PlatformSetting::get('meta_api_key', config('meta_api.api_key')),
            'modelId' => PlatformSetting::get('meta_api_model_id', config('meta_api.model_id')),
            'activeProvider' => PlatformSetting::get('ai_provider_active', config('ai_providers.active')),
            'providers' => config('ai_providers.providers'),
            'providerKeysSet' => collect(['claude', 'openai', 'gemini'])->mapWithKeys(fn ($p) => [
                $p => (bool) PlatformSetting::get("{$p}_api_key", config("ai_providers.providers.{$p}.api_key")),
            ]),
            'pexelsKeySet' => (bool) PlatformSetting::get('pexels_api_key', config('stock_photos.pexels.api_key')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'meta_api_base_url' => ['nullable', 'string', 'max:255'],
            'meta_api_key' => ['nullable', 'string', 'max:255'],
            'meta_api_model_id' => ['nullable', 'string', 'max:255'],
            'ai_provider_active' => ['required', 'string', 'in:meta,claude,openai,gemini'],
            'claude_api_key' => ['nullable', 'string', 'max:255'],
            'openai_api_key' => ['nullable', 'string', 'max:255'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'pexels_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['meta_api_base_url'])) {
            PlatformSetting::set('meta_api_base_url', $validated['meta_api_base_url']);
        }
        if (! empty($validated['meta_api_model_id'])) {
            PlatformSetting::set('meta_api_model_id', $validated['meta_api_model_id']);
        }
        if (! empty($validated['meta_api_key'])) {
            PlatformSetting::set('meta_api_key', $validated['meta_api_key']);
        }

        PlatformSetting::set('ai_provider_active', $validated['ai_provider_active']);

        foreach (['claude', 'openai', 'gemini'] as $provider) {
            if (! empty($validated["{$provider}_api_key"])) {
                PlatformSetting::set("{$provider}_api_key", $validated["{$provider}_api_key"]);
            }
        }

        if (! empty($validated['pexels_api_key'])) {
            PlatformSetting::set('pexels_api_key', $validated['pexels_api_key']);
        }

        return back()->with('status', 'Settings saved.');
    }
}
