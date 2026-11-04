<?php

namespace Tests\Feature;

use App\Models\AnalysisRun;
use App\Models\Plan;
use App\Models\SiteWatch;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $planKey = 'free'): User
    {
        $this->seed(PlanSeeder::class);
        $u = User::factory()->create(['name' => 'Ada Lovelace', 'company_name' => 'Analytical']);
        Subscription::create(['user_id' => $u->id, 'plan_id' => Plan::where('plan_key', $planKey)->value('id'), 'status' => 'active', 'started_at' => now()]);

        return $u;
    }

    private function scan(User $u, string $url, string $status = 'complete'): AnalysisRun
    {
        return AnalysisRun::create(['public_id' => (string) Str::uuid(), 'user_id' => $u->id, 'submitted_url' => $url, 'normalized_url' => $url.'/', 'idempotency_key' => uniqid(), 'status' => $status, 'current_stage' => 'ready', 'source_mode' => 'live']);
    }

    public function test_dashboard_shows_a_card_per_analysis_with_title_and_url_and_the_current_plan(): void
    {
        $u = $this->member('starter');
        $this->scan($u, 'https://www.acme-dental.test');
        $this->scan($u, 'https://other.test', 'running');

        $r = $this->actingAs($u)->get(route('dashboard'))->assertOk();

        $r->assertSee('Recent analyses')->assertSee('acme-dental.test')->assertSee('other.test')->assertSee('Your current plan')->assertSee('Starter');
        $r->assertSee(route('plan.show'), false);
        $r->assertSee('data-theme="light"', false); // light is the default
    }

    public function test_a_user_only_sees_their_own_analyses(): void
    {
        $u = $this->member();
        $this->scan(User::factory()->create(), 'https://someone-else.test');

        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertDontSee('someone-else.test')->assertSee('redone a website yet');
    }

    public function test_plan_page_marks_current_and_labels_upgrades_and_downgrades(): void
    {
        $u = $this->member('starter');

        $r = $this->actingAs($u)->get(route('plan.show'))->assertOk();

        $r->assertSee("You're on this plan", false)->assertSee('Upgrade to Agency')->assertSee('Downgrade to Free');
    }

    public function test_downgrade_warns_when_watching_more_sites_than_the_smaller_plan_allows(): void
    {
        $u = $this->member('starter');
        foreach (['a', 'b', 'c'] as $h) {
            SiteWatch::create(['user_id' => $u->id, 'submitted_url' => "https://{$h}.test", 'normalized_url' => "https://{$h}.test/", 'frequency_days' => 30, 'is_enabled' => true, 'next_scan_at' => now()->addDay()]);
        }

        $this->actingAs($u)->get(route('plan.show'))->assertSee('2 more than Free allows');
    }

    public function test_switching_plan_lands_back_on_the_plan_page(): void
    {
        $u = $this->member('free');
        $starter = Plan::where('plan_key', 'starter')->first();

        $this->actingAs($u)->post(route('plans.select', $starter))->assertRedirect(route('plan.show'));

        $this->assertSame('starter', $u->fresh()->currentPlan()->plan_key);
    }

    public function test_profile_details_can_be_changed_and_email_must_stay_unique(): void
    {
        $u = $this->member();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($u)->patch(route('profile.update'), ['name' => 'Ada B', 'company_name' => '', 'email' => 'new@example.com'])->assertRedirect(route('profile.edit'));
        $u->refresh();
        $this->assertSame(['Ada B', null, 'new@example.com'], [$u->name, $u->company_name, $u->email]);

        $this->actingAs($u)->from(route('profile.edit'))->patch(route('profile.update'), ['name' => 'Ada B', 'email' => $other->email])->assertSessionHasErrors('email');
        $this->assertSame('new@example.com', $u->fresh()->email);
        $this->actingAs($u)->patch(route('profile.update'), ['name' => 'Ada B', 'email' => 'new@example.com'])->assertSessionHasNoErrors(); // keeping your own email is fine
    }

    public function test_password_change_needs_the_current_password(): void
    {
        $u = $this->member();
        $u->update(['password' => 'old-password-123']);

        $this->actingAs($u)->put(route('profile.password'), ['current_password' => 'wrong', 'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9'])->assertSessionHasErrors('current_password', null, 'password');
        $this->assertTrue(Hash::check('old-password-123', $u->fresh()->password));

        $this->actingAs($u)->put(route('profile.password'), ['current_password' => 'old-password-123', 'password' => 'brand-new-pass-9', 'password_confirmation' => 'brand-new-pass-9'])->assertRedirect(route('profile.edit'));
        $this->assertTrue(Hash::check('brand-new-pass-9', $u->fresh()->password));
    }

    public function test_account_pages_require_sign_in(): void
    {
        foreach (['profile.edit', 'plan.show', 'dashboard'] as $name) {
            $this->get(route($name))->assertRedirect();
        }
    }

    public function test_every_themed_layout_defaults_to_light_and_offers_the_toggle(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-theme="light"', false);
        $this->get(route('landing'))->assertOk()->assertSee('data-theme="light"', false)->assertSee('data-theme-toggle', false);
    }
}
