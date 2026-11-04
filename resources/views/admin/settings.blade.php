@extends('layouts.app')

@section('title', 'Settings — REDOLIO Admin')

@section('content')
    @include('admin.partials.header', ['active' => 'settings'])

    <main class="mx-auto max-w-2xl px-8 py-10">
        <h1 class="text-2xl font-bold text-ink">AI settings</h1>
        <p class="mt-1 text-sm text-muted">
            Choose which AI provider powers website analysis and redesign generation on this installation.
        </p>

        @if (session('status'))
            <div class="mt-6 rounded-lg bg-ok-soft px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 flex flex-col gap-6">
            @csrf

            <div class="flex flex-col gap-4 rounded-xl border border-line bg-surface p-6">
                <p class="text-sm font-bold text-ink">AI provider</p>
                <div>
                    <label for="ai_provider_active" class="text-xs font-medium text-muted">Active provider</label>
                    <select id="ai_provider_active" name="ai_provider_active"
                            class="mt-1.5 w-full rounded-lg border border-line bg-surface px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                        @foreach ($providers as $key => $provider)
                            <option value="{{ $key }}" @selected(old('ai_provider_active', $activeProvider) === $key)>{{ $provider['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-xs text-faint">Meta Model API needs no extra key here — configure it below. Claude, ChatGPT and Gemini each need their own key before you can select them.</p>
                </div>

                @foreach (['claude' => 'Claude (Anthropic)', 'openai' => 'ChatGPT (OpenAI)', 'gemini' => 'Gemini (Google)'] as $providerKey => $label)
                    <div>
                        <label for="{{ $providerKey }}_api_key" class="text-xs font-medium text-muted">
                            {{ $label }} API key
                            @if ($providerKeysSet[$providerKey])
                                <span class="text-ok">(currently set — leave blank to keep it)</span>
                            @endif
                        </label>
                        <input id="{{ $providerKey }}_api_key" name="{{ $providerKey }}_api_key" type="password"
                               placeholder="{{ $providerKeysSet[$providerKey] ? '••••••••••••••••' : 'Paste your API key' }}"
                               class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-4 rounded-xl border border-line bg-surface p-6">
                <div>
                    <p class="text-sm font-bold text-ink">Meta Model API</p>
                    <p class="mt-1 text-xs text-faint">Bring your own Meta Model API credentials for this installation.</p>
                </div>
                <div>
                    <label for="meta_api_base_url" class="text-xs font-medium text-muted">API base URL</label>
                    <input id="meta_api_base_url" name="meta_api_base_url" type="text" value="{{ old('meta_api_base_url', $baseUrl) }}"
                           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label for="meta_api_model_id" class="text-xs font-medium text-muted">Model ID</label>
                    <input id="meta_api_model_id" name="meta_api_model_id" type="text" value="{{ old('meta_api_model_id', $modelId) }}"
                           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                </div>
                <div>
                    <label for="meta_api_key" class="text-xs font-medium text-muted">
                        API key
                        @if ($apiKeySet)
                            <span class="text-ok">(currently set — leave blank to keep it)</span>
                        @endif
                    </label>
                    <input id="meta_api_key" name="meta_api_key" type="password" placeholder="{{ $apiKeySet ? '••••••••••••••••' : 'Paste your API key' }}"
                           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                </div>
            </div>

            <div class="flex flex-col gap-4 rounded-xl border border-line bg-surface p-6">
                <div>
                    <p class="text-sm font-bold text-ink">Stock photo fallback (Pexels)</p>
                    <p class="mt-1 text-xs text-faint">
                        When a redesigned site has no suitable hero or section image of its own, REDOLIO fetches one
                        free, licensed stock photo instead of leaving an empty placeholder. Never used when a real
                        original image is available. Get a free key at pexels.com/api — leave blank to disable.
                    </p>
                </div>
                <div>
                    <label for="pexels_api_key" class="text-xs font-medium text-muted">
                        Pexels API key
                        @if ($pexelsKeySet)
                            <span class="text-ok">(currently set — leave blank to keep it)</span>
                        @endif
                    </label>
                    <input id="pexels_api_key" name="pexels_api_key" type="password"
                           placeholder="{{ $pexelsKeySet ? '••••••••••••••••' : 'Paste your API key' }}"
                           class="mt-1.5 w-full rounded-lg border border-line px-3.5 py-2.5 text-sm focus:border-accent focus:outline-none">
                </div>
            </div>

            <button type="submit" class="self-start rounded-full bg-ink px-5 py-2.5 text-sm font-semibold text-canvas hover:opacity-85">
                Save settings
            </button>
        </form>
    </main>
@endsection
