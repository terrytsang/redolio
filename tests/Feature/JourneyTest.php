<?php

namespace Tests\Feature;

use App\Jobs\GenerateDesignSpecJob;
use App\Jobs\RunWebsiteAnalysis;
use App\Models\AnalysisRun;
use App\Models\ApprovedDesign;
use App\Models\DesignSelection;
use App\Models\DiagnosisReport;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VisualQaReport;
use App\Services\Billing\Billing;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

class JourneyTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    private function userOn(string $plan): User
    {
        $u = User::factory()->create();
        Subscription::create(['user_id' => $u->id, 'plan_id' => Plan::where('plan_key', $plan)->value('id'), 'status' => 'active', 'started_at' => now()]);

        return $u;
    }

    private function stripe(): void
    {
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_test', 'services.stripe.prices.starter' => 'price_starter', 'services.stripe.prices.agency' => 'price_agency']);
    }

    private function signed(array $event, ?int $t = null): array
    {
        $payload = json_encode($event);
        $t ??= time();

        return [$payload, ['Stripe-Signature' => "t={$t},v1=".hash_hmac('sha256', $t.'.'.$payload, 'whsec_test'), 'Content-Type' => 'application/json']];
    }

    // ---------------------------------------------------------------- sign up, then scan

    public function test_a_visitor_is_asked_to_sign_up_with_just_email_and_password_then_the_scan_starts(): void
    {
        Queue::fake();
        $this->post('/analyses', ['url' => 'https://example.com'])->assertRedirect(route('register'));
        $this->assertSame(0, AnalysisRun::count(), 'nothing is scanned before an account exists');

        $this->get(route('register'))->assertOk()->assertSee('example.com')->assertDontSee('Confirm password')->assertDontSee('Full name');

        $r = $this->post(route('register.store'), ['email' => 'jo@example.org', 'password' => 'a-good-pass-1']);
        $run = AnalysisRun::firstOrFail();
        $r->assertRedirect(route('analyses.show', $run->public_id));
        $user = User::where('email', 'jo@example.org')->firstOrFail();
        $this->assertSame($user->id, $run->user_id);
        $this->assertSame('free', $user->currentPlan()->plan_key);
        $this->assertSame('Jo', $user->name, 'a friendly name is derived; it can be changed in Profile');
        Queue::assertPushed(RunWebsiteAnalysis::class);
    }

    public function test_an_invalid_address_is_refused_before_asking_anyone_to_sign_up(): void
    {
        $this->from('/')->post('/analyses', ['url' => 'http://127.0.0.1/'])->assertSessionHasErrors('url');
        $this->assertNull(session('pending_analysis'));
    }

    public function test_logging_in_also_starts_a_pending_scan_and_signed_in_people_start_at_once(): void
    {
        Queue::fake();
        $user = $this->userOn('starter');
        $this->post('/analyses', ['url' => 'https://example.com']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('analyses.show', AnalysisRun::firstOrFail()->public_id));

        $this->actingAs($user)->post('/analyses', ['url' => 'https://example.org'])->assertRedirect();
        $this->assertSame(2, AnalysisRun::count());
    }

    public function test_registration_needs_only_two_fields_and_rejects_short_passwords_and_duplicates(): void
    {
        $this->post(route('register.store'), ['email' => 'a@b.co', 'password' => 'short'])->assertSessionHasErrors('password');
        User::factory()->create(['email' => 'taken@example.org']);
        $this->post(route('register.store'), ['email' => 'taken@example.org', 'password' => 'a-good-pass-1'])->assertSessionHasErrors('email');
    }

    // ---------------------------------------------------------------- Google

    private function google(array $over = []): void
    {
        config(['services.google.client_id' => 'web-client-id.apps.googleusercontent.com']);
        Http::fake(['oauth2.googleapis.com/tokeninfo*' => Http::response($over + ['aud' => 'web-client-id.apps.googleusercontent.com', 'iss' => 'https://accounts.google.com', 'sub' => 'g-123', 'email' => 'Gina@Example.org', 'email_verified' => 'true', 'name' => 'Gina Lee', 'exp' => (string) (time() + 600)])]);
    }

    public function test_google_sign_up_creates_a_free_account_and_starts_the_pending_scan(): void
    {
        Queue::fake();
        $this->google();
        $this->post('/analyses', ['url' => 'https://example.com']);

        $r = $this->postJson(route('auth.google.token'), ['credential' => 'jwt.token.here'])->assertOk();
        $run = AnalysisRun::firstOrFail();
        $this->assertSame(route('analyses.show', $run->public_id), $r->json('redirect'));
        $u = User::where('email', 'gina@example.org')->firstOrFail();
        $this->assertSame('g-123', $u->google_id);
        $this->assertSame('Gina Lee', $u->name);
        $this->assertSame('free', $u->currentPlan()->plan_key);
        $this->assertAuthenticatedAs($u);
    }

    public function test_google_links_an_existing_account(): void
    {
        $this->google();
        $existing = User::factory()->create(['email' => 'gina@example.org']);
        $this->postJson(route('auth.google.token'), ['credential' => 'jwt'])->assertOk();
        $this->assertAuthenticatedAs($existing);
        $this->assertSame('g-123', $existing->fresh()->google_id);
        $this->assertSame(1, User::where('email', 'gina@example.org')->count());
    }

    public function test_google_tokens_for_another_app_unverified_or_expired_are_refused(): void
    {
        foreach ([['aud' => 'someone-elses-client'], ['email_verified' => 'false'], ['exp' => (string) (time() - 5)], ['iss' => 'https://evil.example']] as $bad) {
            $this->google($bad);
            $this->postJson(route('auth.google.token'), ['credential' => 'jwt'])->assertStatus(422);
            $this->assertGuest();
        }
        $this->assertSame(0, User::count());
    }

    public function test_google_shows_only_when_a_client_id_exists_and_the_web_client_id_name_works(): void
    {
        config(['services.google.client_id' => null]);
        $this->get(route('register'))->assertOk()->assertDontSee('g_id_onload', false);
        $this->postJson(route('auth.google.token'), ['credential' => 'x'])->assertStatus(503);

        config(['services.google.client_id' => 'abc.apps.googleusercontent.com']);
        $this->get(route('register'))->assertOk()->assertSee('g_id_onload', false)->assertSee('abc.apps.googleusercontent.com')->assertSee('signup_with', false);
        $this->get(route('login'))->assertOk()->assertSee('signin_with', false);
    }

    // ---------------------------------------------------------------- the plan wall

    private function project(User $u): AnalysisRun
    {
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => $u->id]);
        [$res] = $this->attach($run, $this->legacySpec());
        $this->assertTrue($res['attached']);

        return $run;
    }

    public function test_approving_on_the_free_plan_leads_to_the_upgrade_page_and_approves_nothing(): void
    {
        $u = $this->userOn('free');
        $run = $this->project($u);
        $this->actingAs($u)->post(route('analyses.approve-design', $run->public_id))->assertRedirect(route('analyses.upgrade', [$run->public_id, 'intent' => 'approve']));
        $this->assertSame(0, ApprovedDesign::count());
        $this->actingAs($u)->get(route('analyses.upgrade', $run->public_id))->assertOk()->assertSee('Starter')->assertSee('Agency')->assertSee('Your layout is saved');
        $this->actingAs($u)->get(route('analyses.publish', $run->public_id))->assertRedirect(route('pricing'));
        $this->actingAs($u)->post(route('analyses.exports.store', $run->public_id))->assertRedirect(route('pricing'));
    }

    public function test_paid_plans_approve_publish_and_export_normally_and_skip_the_upgrade_page(): void
    {
        $u = $this->userOn('starter');
        $run = $this->project($u);
        $this->actingAs($u)->post(route('analyses.approve-design', $run->public_id))->assertRedirect(route('analyses.launch', $run->public_id));
        $this->assertSame(1, ApprovedDesign::count());
        $this->actingAs($u)->get(route('analyses.upgrade', $run->public_id))->assertRedirect(route('analyses.launch', $run->public_id));
    }

    public function test_the_editor_tells_free_users_that_saving_leads_to_a_plan(): void
    {
        $free = $this->userOn('free');
        $run = $this->project($free);
        $this->actingAs($free)->get(route('analyses.edit', $run->public_id))->assertOk()->assertSee('Layout saved')->assertSee('See plans');
    }

    // ---------------------------------------------------------------- Stripe

    public function test_choosing_a_paid_plan_goes_to_stripe_checkout_with_the_right_price_and_metadata(): void
    {
        $this->stripe();
        $u = $this->userOn('free');
        $run = $this->project($u);
        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_1']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
        ]);

        $this->actingAs($u)->post(route('plans.select', Plan::where('plan_key', 'starter')->first()), ['run' => $run->public_id, 'intent' => 'approve'])->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        $this->assertSame('cus_1', $u->fresh()->stripe_customer_id);
        Http::assertSent(function ($r) use ($u) {
            if (! str_ends_with($r->url(), '/checkout/sessions')) {
                return false;
            }
            $d = $r->data();

            return $d['mode'] === 'subscription' && $d['line_items'][0]['price'] === 'price_starter' && $d['client_reference_id'] === (string) $u->id && $d['metadata']['plan_key'] === 'starter' && str_contains($d['success_url'], '{CHECKOUT_SESSION_ID}');
        });
        $this->assertSame('free', $u->fresh()->currentPlan()->plan_key, 'nothing unlocks before payment is confirmed');
    }

    public function test_the_success_redirect_confirms_with_stripe_then_unlocks_and_approves(): void
    {
        $this->stripe();
        $u = $this->userOn('free');
        $run = $this->project($u);
        Http::fake(['api.stripe.com/v1/customers' => Http::response(['id' => 'cus_1']), 'api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.com/x']),
            'api.stripe.com/v1/checkout/sessions/cs_test_1*' => Http::response(['id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'client_reference_id' => (string) $u->id, 'customer' => 'cus_1', 'metadata' => ['plan_key' => 'starter'], 'subscription' => ['id' => 'sub_1', 'items' => ['data' => [['current_period_end' => now()->addMonth()->timestamp]]]]])]);
        $this->actingAs($u)->post(route('plans.select', Plan::where('plan_key', 'starter')->first()), ['run' => $run->public_id, 'intent' => 'approve']);

        $this->actingAs($u)->get(route('billing.success', ['session_id' => 'cs_test_1']))->assertRedirect(route('analyses.launch', $run->public_id));
        $u = $u->fresh();
        $this->assertSame('starter', $u->currentPlan()->plan_key);
        $this->assertTrue($u->canPublish());
        $this->assertSame('sub_1', $u->currentSubscription()->stripe_subscription_id);
        $this->assertSame(1, ApprovedDesign::count());
        // opening the success URL again does not create a second subscription
        $this->actingAs($u)->get(route('billing.success', ['session_id' => 'cs_test_1']));
        $this->assertSame(1, Subscription::where('stripe_subscription_id', 'sub_1')->count());
    }

    public function test_someone_elses_checkout_session_cannot_unlock_a_plan(): void
    {
        $this->stripe();
        $u = $this->userOn('free');
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_other*' => Http::response(['status' => 'complete', 'payment_status' => 'paid', 'client_reference_id' => '99999', 'metadata' => ['plan_key' => 'agency'], 'subscription' => 'sub_x'])]);
        $this->actingAs($u)->get(route('billing.success', ['session_id' => 'cs_other']))->assertRedirect(route('plan.show'));
        $this->assertSame('free', $u->fresh()->currentPlan()->plan_key);
    }

    public function test_webhooks_need_a_valid_signature_and_drive_the_subscription_lifecycle(): void
    {
        $this->stripe();
        $u = $this->userOn('free');
        $this->postJson('/stripe/webhook', ['type' => 'x'], ['Stripe-Signature' => 't=1,v1=bad'])->assertStatus(400);
        [$old, $oldHeaders] = $this->signed(['type' => 'x'], time() - 4000);
        $this->call('POST', '/stripe/webhook', [], [], [], $this->server($oldHeaders), $old)->assertStatus(400);

        $completed = ['type' => 'checkout.session.completed', 'data' => ['object' => ['mode' => 'subscription', 'payment_status' => 'paid', 'client_reference_id' => (string) $u->id, 'customer' => 'cus_9', 'subscription' => 'sub_9', 'metadata' => ['plan_key' => 'agency']]]];
        [$payload, $headers] = $this->signed($completed);
        $this->call('POST', '/stripe/webhook', [], [], [], $this->server($headers), $payload)->assertOk();
        $this->call('POST', '/stripe/webhook', [], [], [], $this->server($headers), $payload)->assertOk(); // Stripe retries
        $u = $u->fresh();
        $this->assertSame('agency', $u->currentPlan()->plan_key);
        $this->assertSame(1, $u->subscriptions()->where('status', 'active')->count());

        [$payload, $headers] = $this->signed(['type' => 'customer.subscription.updated', 'data' => ['object' => ['id' => 'sub_9', 'status' => 'active', 'cancel_at_period_end' => true, 'items' => ['data' => [['price' => ['id' => 'price_starter'], 'current_period_end' => now()->addDays(10)->timestamp]]]]]]);
        $this->call('POST', '/stripe/webhook', [], [], [], $this->server($headers), $payload)->assertOk();
        $this->assertSame('starter', $u->fresh()->currentPlan()->plan_key, 'a plan change made in Stripe is mirrored');
        $this->assertTrue($u->fresh()->currentSubscription()->cancel_at_period_end);

        [$payload, $headers] = $this->signed(['type' => 'customer.subscription.deleted', 'data' => ['object' => ['id' => 'sub_9']]]);
        $this->call('POST', '/stripe/webhook', [], [], [], $this->server($headers), $payload)->assertOk();
        $this->assertSame('free', $u->fresh()->currentPlan()->plan_key);
        $this->assertFalse($u->fresh()->canPublish());
    }

    private function server(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) {
            $out['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }
        $out['CONTENT_TYPE'] = 'application/json';

        return $out;
    }

    public function test_without_stripe_keys_paid_plans_only_switch_in_development(): void
    {
        $u = $this->userOn('free');
        $this->actingAs($u)->post(route('plans.select', Plan::where('plan_key', 'starter')->first()))->assertRedirect(route('plan.show'));
        $this->assertSame('starter', $u->fresh()->currentPlan()->plan_key, 'testing/local: instant switch so the flow can be tried');

        $this->partialMock(Billing::class, fn ($m) => $m->shouldReceive('devSwitchAllowed')->andReturn(false));
        $p = $this->userOn('free');
        $this->actingAs($p)->post(route('plans.select', Plan::where('plan_key', 'agency')->first()))->assertSessionHas('status');
        $this->assertSame('free', $p->fresh()->currentPlan()->plan_key, 'production without Stripe never hands out paid plans');
    }

    public function test_going_back_to_free_keeps_a_paid_plan_until_the_period_ends(): void
    {
        $this->stripe();
        $u = $this->userOn('starter');
        $u->currentSubscription()->update(['stripe_subscription_id' => 'sub_5']);
        Http::fake(['api.stripe.com/v1/subscriptions/sub_5' => Http::response(['id' => 'sub_5', 'cancel_at_period_end' => true, 'items' => ['data' => [['current_period_end' => now()->addDays(12)->timestamp]]]])]);
        $this->actingAs($u)->post(route('plans.select', Plan::where('plan_key', 'free')->first()))->assertRedirect(route('plan.show'));
        $this->assertSame('starter', $u->fresh()->currentPlan()->plan_key);
        $this->assertTrue($u->fresh()->currentSubscription()->cancel_at_period_end);
    }

    public function test_after_the_scan_the_express_route_builds_the_redo_with_the_best_direction_and_layout(): void
    {
        Queue::fake();
        $u = $this->userOn('free');
        $run = $this->makeRun(null, ['hero1' => 'hero_image']);
        $run->update(['user_id' => $u->id]);
        DiagnosisReport::create(['analysis_run_id' => $run->id, 'version' => 1, 'issues_json' => [], 'summary' => 'x', 'model_identifier' => 't', 'prompt_version' => 'v1', 'created_at' => now()]);
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);

        $r = $this->actingAs($u)->get(route('analyses.express', $run->public_id));
        $sel = $run->designSelections()->first();
        $this->assertNotNull($sel, 'a direction was chosen for them');
        $this->assertNotNull($sel->blueprint_id, 'with a recommended, editable layout');
        $r->assertRedirect(route('analyses.design-directions.generating', [$run->public_id, $sel->id]));
        Queue::assertPushed(GenerateDesignSpecJob::class);

        $this->actingAs($u)->get(route('analyses.express', $run->public_id))->assertRedirect(route('analyses.design-directions.generating', [$run->public_id, $sel->id]));
        $this->assertSame(1, $run->designSelections()->count(), 'following the link twice never starts a second build');
        $this->actingAs(User::factory()->create())->get(route('analyses.express', $run->public_id))->assertNotFound();
    }

    public function test_the_approve_page_shows_website_and_mobile_screenshots_at_the_bottom(): void
    {
        $u = $this->userOn('starter');
        $run = $this->project($u);
        $spec = $run->currentDesignSpec();

        $this->actingAs($u)->get(route('analyses.launch', $run->public_id))->assertOk()->assertSee('Capturing the website and mobile views');

        VisualQaReport::create(['analysis_run_id' => $run->id, 'design_spec_id' => $spec->id, 'status' => 'complete', 'report_json' => ['screenshot_urls' => ['desktop' => '/storage/qa/d.png', 'mobile' => '/storage/qa/m.png']], 'created_at' => now()]);
        $this->actingAs($u)->get(route('analyses.launch', $run->public_id))->assertOk()
            ->assertSee('Approve this design to continue')->assertSee('What you’re approving', false)->assertSee('/storage/qa/d.png')->assertSee('/storage/qa/m.png')->assertSee('Website')->assertSee('Mobile');
    }

    public function test_the_generating_page_keeps_people_company_and_follows_the_redirect_when_done(): void
    {
        $u = $this->userOn('free');
        $run = $this->makeRun(null, []);
        $run->update(['user_id' => $u->id]);
        $this->seed(DesignDirectionSeeder::class);
        $sel = DesignSelection::create(['analysis_run_id' => $run->id, 'direction_key' => 'trusted-expertise', 'direction_version' => 1, 'preferences_json' => [], 'selected_at' => now(), 'status' => 'generating', 'catalogue_version' => 'x']);

        $this->actingAs($u)->get(route('analyses.design-directions.generating', [$run->public_id, $sel->id]))->assertOk()
            ->assertSee('cup of coffee', false)->assertSee('Call someone you love', false)->assertSee('email', false)->assertSee('res.redirected', false)->assertDontSee('location.reload()', false);
    }

    public function test_the_sign_up_page_has_a_working_log_in_link_and_the_glow_never_wraps_the_content(): void
    {
        $html = $this->get(route('register'))->assertOk()->assertSee('Already have an account?')->assertSee(route('login'), false)->getContent();
        $this->assertStringNotContainsString('page-glow flex', $html, 'the glow is a separate decorative layer — on the container it disables clicks below it');
        $this->assertStringContainsString('<div class="page-glow"></div>', $html);
        $this->get(route('login'))->assertOk()->assertSee('Log in');
    }

    public function test_the_brand_is_redolio_with_a_striped_lio_but_the_redo_action_labels_stay(): void
    {
        $landing = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('brand/logo-light.png', $landing, 'the light-background logo is the default');
        $this->assertStringContainsString('brand/logo-dark.png', $landing, 'and the dark-background logo is there for dark mode');
        $this->assertStringContainsString('<link rel="icon" href="', $landing);
        $this->assertStringContainsString('<meta property="og:image" content="'.url('brand/og-image.png').'">', $landing);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $landing);
        foreach (['favicon.ico', 'apple-touch-icon.png', 'favicon-32x32.png', 'favicon-16x16.png', 'brand/logo-light.png', 'brand/logo-dark.png', 'brand/redolio-logo-lightbg.png', 'brand/redolio-logo-darkbg.png'] as $f) {
            $this->assertFileExists(public_path($f), $f);
        }
        $this->assertSame([1200, 630], array_slice(getimagesize(public_path('brand/og-image.png')), 0, 2));
        $this->assertStringContainsString("Let's REDO it.", $landing);
        $this->assertStringContainsString('REDO PREVIEW', $landing);
        $this->assertStringContainsString('REDOLIO analyses your website', $landing);
        $this->assertStringContainsString('© 2026 REDOLIO', $landing);
        $this->assertMatchesRegularExpression('#A product of <a href="https://appition\.com" target="_blank" rel="noopener noreferrer"[^>]*>Appition</a>\.#', $landing);
        foreach (['GitHub', 'Twitter', 'LinkedIn'] as $gone) {
            $this->assertStringNotContainsString($gone, $landing, "the footer no longer lists {$gone}");
        }
        $this->assertStringContainsString('<title>REDOLIO | AI Website Transformation Platform to Build Better Digital Experiences</title>', $landing);
        $this->assertStringNotContainsString('© 2026 REDO.', $landing);
    }

    public function test_the_homepage_has_no_demo_links(): void
    {
        $this->seed(\Database\Seeders\DemoFixturesSeeder::class);
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('Try a demo', $html);
        $this->assertStringNotContainsString('/demo/', $html, 'no link to a demo website');
        $this->assertStringNotContainsString('href="#demo"', $html);
        $this->assertStringNotContainsString('Demo Websites', $html);
        $this->assertStringNotContainsString('Demo businesses', $html);
    }
}
