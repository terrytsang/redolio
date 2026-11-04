<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

/**
 * First-run defaults for a fresh install: the active AI provider is the Meta Model API, with its verified base URL and model already
 * filled in under Admin -> Settings -> Meta Model API. The API KEY is deliberately never seeded: the admin adds their own there
 * (or in .env as META_API_KEY). Existing choices are never overwritten, so re-running the seeder is safe.
 */
class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'ai_provider_active' => 'meta',
            'meta_api_base_url' => (string) config('meta_api.base_url'),
            'meta_api_model_id' => (string) config('meta_api.model_id'),
        ] as $key => $value) {
            if (PlatformSetting::get($key) === null) {
                PlatformSetting::set($key, $value);
            }
        }

        $this->command?->info('AI provider: Meta Model API (add your API key in Admin > Settings > Meta Model API).');
    }
}
