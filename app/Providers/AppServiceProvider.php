<?php

namespace App\Providers;

use App\Models\PlatformSetting;
use App\Services\MetaApi\MetaModelClient;
use App\Services\VisualQa\BrowserlessBrowser;
use App\Services\VisualQa\BrowserbaseBrowser;
use App\Services\VisualQa\FallbackBrowser;
use App\Services\VisualQa\BrowserService;
use App\Services\VisualQa\PlaywrightScreenshotService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MetaModelClient::class, function () {
            $active = config('ai_providers.active', 'meta');
            $client = config("ai_providers.providers.{$active}.client")
                ?? config('ai_providers.providers.meta.client');

            return $this->app->make($client);
        });

        // Provider-independent browser inspection: swap where Chromium runs without touching QA logic.
        $this->app->bind(BrowserService::class, function () {
            $make = fn (string $provider): BrowserService => match ($provider) {
                'browserless' => new BrowserlessBrowser(
                    (string) config('redo.browser.browserless_url'),
                    config('redo.browser.browserless_token'),
                    (int) config('redo.browser.timeout_seconds', 60),
                ),
                'browserbase' => new BrowserbaseBrowser(
                    config('redo.browser.browserbase_api_key'),
                    config('redo.browser.browserbase_project_id'),
                    (string) config('redo.browser.node_binary', 'node'),
                ),
                default => $this->app->make(PlaywrightScreenshotService::class),
            };
            $primary = (string) config('redo.browser.provider');
            $backup = (string) config('redo.browser.fallback');
            $browser = $make($primary);

            // A second provider takes over automatically if the first fails (e.g. its free quota is used up).
            return $backup !== '' && $backup !== $primary ? new FallbackBrowser($browser, $make($backup)) : $browser;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyPlatformSettingOverrides();
    }

    /**
     * Installs can bring their own Meta API key, choose a different AI
     * provider, and supply that provider's key from the admin Settings
     * page instead of .env — stored in platform_settings and layered over
     * config here so the model clients need no awareness of where the
     * credentials came from. Nothing is saved until an admin saves it, so
     * .env keeps working on its own.
     */
    private function applyPlatformSettingOverrides(): void
    {
        try {
            if (! Schema::hasTable('platform_settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        if ($provider = PlatformSetting::get('ai_provider_active')) {
            config(['ai_providers.active' => $provider]);
        }

        foreach (['claude', 'openai', 'gemini'] as $provider) {
            if ($key = PlatformSetting::get("{$provider}_api_key")) {
                config(["ai_providers.providers.{$provider}.api_key" => $key]);
            }
        }

        if ($pexelsKey = PlatformSetting::get('pexels_api_key')) {
            config(['stock_photos.pexels.api_key' => $pexelsKey]);
        }

        // The Meta Model API connection saved by the admin (Admin -> Settings) overrides .env, in every edition.
        if ($baseUrl = PlatformSetting::get('meta_api_base_url')) {
            config(['meta_api.base_url' => $baseUrl]);
        }
        if ($apiKey = PlatformSetting::get('meta_api_key')) {
            config(['meta_api.api_key' => $apiKey]);
        }
        if ($modelId = PlatformSetting::get('meta_api_model_id')) {
            config(['meta_api.model_id' => $modelId]);
        }
    }
}
