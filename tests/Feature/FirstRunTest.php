<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Blueprint\BlueprintCatalog;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** What someone who downloads the project gets on a fresh install: a first admin, Meta as the provider, defaults pre-filled, no key. */
class FirstRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_seed_creates_the_first_admin_with_a_random_password_and_no_default_password_in_the_source(): void
    {
        config(['redo.admin_email' => null, 'redo.admin_password' => null]);

        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])->expectsOutputToContain('First admin created')->assertSuccessful();

        $admin = User::where('role', 'admin')->first();
        $this->assertSame('admin@example.com', $admin->email);
        $this->assertTrue($admin->is_active);
        $this->assertNotNull($admin->password);

        // Running it again changes nothing (the printed password stays the only one).
        $hash = $admin->password;
        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])->expectsOutputToContain('already exists')->assertSuccessful();
        $this->assertSame($hash, $admin->fresh()->password);
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_admin_credentials_from_env_are_used_and_can_be_rotated(): void
    {
        config(['redo.admin_email' => 'me@mydomain.test', 'redo.admin_password' => 'a-strong-passphrase-123']);
        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])->assertSuccessful();
        $this->assertTrue(Hash::check('a-strong-passphrase-123', User::where('email', 'me@mydomain.test')->value('password')));

        config(['redo.admin_password' => 'a-new-passphrase-456']);
        $this->artisan('db:seed', ['--class' => AdminUserSeeder::class])->assertSuccessful();
        $this->assertTrue(Hash::check('a-new-passphrase-456', User::where('email', 'me@mydomain.test')->value('password')));
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_the_active_provider_is_meta_with_its_url_and_model_prefilled_and_never_a_key(): void
    {
        $this->seed(PlatformSettingsSeeder::class);

        $this->assertSame('meta', PlatformSetting::get('ai_provider_active'));
        $this->assertSame('https://api.meta.ai/v1', PlatformSetting::get('meta_api_base_url'));
        $this->assertSame('muse-spark-1.3', PlatformSetting::get('meta_api_model_id'));
        $this->assertNull(PlatformSetting::get('meta_api_key'), 'the API key is never seeded');
        $this->assertSame('', (string) config('meta_api.api_key'));

        // A choice the admin already made is not overwritten by re-seeding.
        PlatformSetting::set('meta_api_model_id', 'muse-spark-9');
        $this->seed(PlatformSettingsSeeder::class);
        $this->assertSame('muse-spark-9', PlatformSetting::get('meta_api_model_id'));
    }

    public function test_the_admin_settings_page_shows_meta_as_active_with_url_and_model_prefilled_and_saves_the_key_encrypted(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['redo.admin_email' => null]);
        $admin = User::factory()->create(['role' => 'admin']);

        $page = $this->actingAs($admin)->get(route('admin.settings'))->assertOk();
        $page->assertSee('Meta Model API')->assertSee('value="https://api.meta.ai/v1"', false)->assertSee('value="muse-spark-1.3"', false)->assertSee('Paste your API key');
        $this->assertMatchesRegularExpression('/<option value="meta"[^>]*selected/', $page->getContent());
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('add your Meta Model API key');

        $secret = 'meta-test-key-DO-NOT-LEAK-123456';
        $this->actingAs($admin)->post(route('admin.settings.update'), ['ai_provider_active' => 'meta', 'meta_api_base_url' => 'https://api.meta.ai/v1', 'meta_api_model_id' => 'muse-spark-1.3', 'meta_api_key' => $secret])->assertRedirect();

        $stored = DB::table('platform_settings')->where('key', 'meta_api_key')->value('value');
        $this->assertNotSame($secret, $stored, 'stored encrypted, not as readable text');
        $this->assertStringNotContainsString($secret, $stored);
        $this->assertSame($secret, Crypt::decryptString($stored));
        $this->assertSame($secret, PlatformSetting::get('meta_api_key'));
        $this->assertStringNotContainsString($secret, (string) cache()->get('platform_setting:meta_api_key'), 'a readable key is never written to the cache');

        // The settings page never prints the key back, and the dashboard prompt is gone.
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertDontSee($secret)->assertSee('currently set');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('add your Meta Model API key');
    }

    public function test_a_saved_key_is_used_by_the_model_client_in_every_edition(): void
    {
        foreach (['selfhosted', 'saas'] as $edition) {
            config(['redo.edition' => $edition, 'meta_api.api_key' => null]);
            PlatformSetting::set('meta_api_key', 'saved-in-settings-'.$edition);
            (new \App\Providers\AppServiceProvider($this->app))->boot();
            $this->assertSame('saved-in-settings-'.$edition, config('meta_api.api_key'), "{$edition}: the admin's saved key is applied");
        }
    }

    public function test_a_fresh_install_with_no_captured_previews_still_has_ready_layouts_but_failed_previews_are_respected(): void
    {
        Storage::fake('public');
        $catalog = app(BlueprintCatalog::class);
        $this->assertNotEmpty($catalog->forDirection('trusted-expertise'));
        $this->assertSame('ready', $catalog->effectiveStatus($catalog->find('executive-corporate-01')), 'no previews captured yet: works out of the box');

        Storage::disk('public')->put('blueprint-previews/executive-corporate-01/executive-clarity/manifest.json', json_encode(['passed' => false]));
        $fresh = new BlueprintCatalog(app(\App\Services\Blueprint\SectionRegistry::class), app(\App\Services\Catalogue\DesignCatalogue::class));
        $this->assertSame('draft', $fresh->effectiveStatus($fresh->find('executive-corporate-01')), 'once previews exist, a layout that did not pass is not ready');
    }
}
