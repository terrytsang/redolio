<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfHostedSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['redo.edition' => 'selfhosted']);
    }

    public function test_requests_redirect_to_setup_wizard_when_no_admin_exists(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('setup.index'));
    }

    public function test_setup_wizard_is_reachable(): void
    {
        $response = $this->get('/setup');

        $response->assertOk();
    }

    public function test_dashboard_is_blocked_until_admin_exists(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('setup.index'));
    }

    public function test_analyses_routes_remain_reachable_during_setup(): void
    {
        $response = $this->from('/')->post('/analyses', ['url' => 'not a url']);

        $response->assertSessionHasErrors('url');
    }

    public function test_saving_api_key_persists_to_platform_settings(): void
    {
        $this->post('/setup/api-key', [
            'meta_api_base_url' => 'https://api.meta.ai/v1',
            'meta_api_model_id' => 'test-model',
            'meta_api_key' => 'test-key',
        ])->assertRedirect(route('setup.index'));

        $this->assertSame('https://api.meta.ai/v1', PlatformSetting::get('meta_api_base_url'));
        $this->assertSame('test-key', PlatformSetting::get('meta_api_key'));
    }

    public function test_creating_admin_account_unlocks_the_app(): void
    {
        $response = $this->post('/setup/admin', [
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(User::where('email', 'admin@example.test')->first()->isAdmin());

        $this->get('/')->assertOk();
    }
}
