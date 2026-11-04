<?php

namespace Tests\Feature;

use App\Models\AnalysisRun;
use App\Models\OneTimePurchase;
use App\Models\PageView;
use App\Models\Plan;
use App\Models\SiteWatch;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Analytics\VisitClassifier;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentsAndAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        config(['services.stripe.secret' => 'sk_test_x', 'services.stripe.webhook_secret' => 'whsec_x',
            'services.stripe.prices' => ['starter' => 'price_starter', 'agency' => 'price_agency'], 'services.stripe.export_price' => 'price_export']);
    }

    private function onFree(User $u): void
    {
        Subscription::create(['user_id' => $u->id, 'plan_id' => Plan::where('plan_key', 'free')->value('id'), 'status' => 'active', 'started_at' => now()]);
    }

    public function test_subscribing_goes_to_stripe_checkout_and_success_activates_the_plan_and_saves_the_payment(): void
    {
        $u = User::factory()->create();
        $this->onFree($u);
        $starter = Plan::where('plan_key', 'starter')->first();
        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_1']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_1']),
        ]);

        $this->actingAs($u)->post(route('plans.select', $starter))->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_1');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/checkout/sessions') && $r['mode'] === 'subscription' && $r['line_items'][0]['price'] === 'price_starter');

        Http::fake([
            'api.stripe.com/v1/checkout/sessions/cs_test_1*' => Http::response([
                'id' => 'cs_test_1', 'status' => 'complete', 'payment_status' => 'paid', 'client_reference_id' => (string) $u->id, 'customer' => 'cus_1',
                'amount_total' => 2900, 'currency' => 'usd', 'invoice' => 'in_1', 'metadata' => ['plan_key' => 'starter'],
                'subscription' => ['id' => 'sub_1', 'items' => ['data' => [['current_period_end' => now()->addMonth()->timestamp]]]],
            ]),
        ]);
        $this->actingAs($u)->get(route('billing.success', ['session_id' => 'cs_test_1']))->assertRedirect(route('plan.show'));
        $this->actingAs($u)->get(route('billing.success', ['session_id' => 'cs_test_1'])); // refreshing the page changes nothing

        $this->assertSame('starter', $u->fresh()->currentPlan()->plan_key);
        $this->assertSame(1, Subscription::where('stripe_subscription_id', 'sub_1')->count());
        $tx = Transaction::where('user_id', $u->id)->get();
        $this->assertCount(1, $tx);
        $this->assertSame([2900, 'subscription', 'paid'], [$tx[0]->amount_cents, $tx[0]->type, $tx[0]->status]);
    }

    public function test_a_starter_can_upgrade_to_agency_through_stripe(): void
    {
        $u = User::factory()->create();
        Subscription::create(['user_id' => $u->id, 'plan_id' => Plan::where('plan_key', 'starter')->value('id'), 'status' => 'active', 'started_at' => now(), 'stripe_subscription_id' => 'sub_9']);
        Http::fake([
            'api.stripe.com/v1/subscriptions/sub_9' => Http::sequence()->push(['items' => ['data' => [['id' => 'si_1']]]])->push(['items' => ['data' => [['current_period_end' => now()->addMonth()->timestamp]]]]),
        ]);

        $this->actingAs($u)->post(route('plans.select', Plan::where('plan_key', 'agency')->first()))->assertRedirect(route('plan.show'));

        $this->assertSame('agency', $u->fresh()->currentPlan()->plan_key);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && ($r['items'][0]['price'] ?? null) === 'price_agency');
    }

    public function test_the_export_pass_is_paid_once_confirmed_with_stripe_and_unlocks_one_download(): void
    {
        $u = User::factory()->create();
        $this->onFree($u);
        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_2']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_pass_1', 'url' => 'https://checkout.stripe.com/c/pay/cs_pass_1']),
        ]);
        $this->actingAs($u)->post(route('export-pass.purchase'))->assertRedirect('https://checkout.stripe.com/c/pay/cs_pass_1');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/checkout/sessions') && $r['mode'] === 'payment' && $r['line_items'][0]['price'] === 'price_export');
        $this->assertSame(0, OneTimePurchase::count(), 'nothing is granted before payment');
        $this->assertFalse($u->canExportStatic());

        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_pass_1' => Http::response([
            'id' => 'cs_pass_1', 'status' => 'complete', 'payment_status' => 'paid', 'client_reference_id' => (string) $u->id, 'amount_total' => 2500, 'currency' => 'usd',
            'payment_intent' => 'pi_1', 'metadata' => ['type' => 'export_pass'],
        ])]);
        $this->actingAs($u)->get(route('export-pass.success', ['session_id' => 'cs_pass_1']))->assertRedirect(route('plan.show'));
        $this->actingAs($u)->get(route('export-pass.success', ['session_id' => 'cs_pass_1']));

        $this->assertSame(1, OneTimePurchase::where('user_id', $u->id)->count());
        $this->assertSame(3, $u->fresh()->availableExportPassCredits(), 'a pass includes the first download plus two more');
        $this->assertTrue($u->fresh()->canExportStatic());
        $this->assertFalse($u->fresh()->canPublish(), 'an Export Pass never unlocks publishing on REDOLIO');
        $tx = Transaction::where('type', 'export_pass')->get();
        $this->assertSame([1, 2500], [$tx->count(), $tx[0]->amount_cents]);
    }

    public function test_an_unpaid_or_someone_elses_export_pass_session_grants_nothing(): void
    {
        $u = User::factory()->create();
        $other = User::factory()->create();
        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_a' => Http::response(['id' => 'cs_a', 'status' => 'open', 'payment_status' => 'unpaid', 'client_reference_id' => (string) $u->id, 'metadata' => ['type' => 'export_pass']]),
            'api.stripe.com/v1/checkout/sessions/cs_b' => Http::response(['id' => 'cs_b', 'status' => 'complete', 'payment_status' => 'paid', 'client_reference_id' => (string) $other->id, 'metadata' => ['type' => 'export_pass']])]);

        $this->actingAs($u)->get(route('export-pass.success', ['session_id' => 'cs_a']));
        $this->actingAs($u)->get(route('export-pass.success', ['session_id' => 'cs_b']));

        $this->assertSame(0, OneTimePurchase::count());
    }

    public function test_without_stripe_keys_production_never_hands_out_a_free_pass(): void
    {
        config(['services.stripe.secret' => '']);
        $this->app['env'] = 'production';
        User::factory()->create(['role' => 'admin']);
        $u = User::factory()->create();
        $this->actingAs($u)->withSession(['_token' => 't'])->post(route('export-pass.purchase'), ['_token' => 't'])->assertSessionHas('status');
        $this->assertSame(0, OneTimePurchase::count());
    }

    public function test_the_webhook_saves_renewals_and_fulfils_export_passes(): void
    {
        $u = User::factory()->create();
        Subscription::create(['user_id' => $u->id, 'plan_id' => Plan::where('plan_key', 'starter')->value('id'), 'status' => 'active', 'started_at' => now()->subMonth(), 'stripe_subscription_id' => 'sub_r']);
        $send = function (array $event) {
            $body = json_encode($event);
            $t = time();

            return $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t=$t,v1=".hash_hmac('sha256', "$t.$body", 'whsec_x'), 'CONTENT_TYPE' => 'application/json'], $body);
        };

        $send(['type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_renew', 'subscription' => 'sub_r', 'amount_paid' => 2900, 'currency' => 'usd']]])->assertOk();
        $send(['type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_renew', 'subscription' => 'sub_r', 'amount_paid' => 2900, 'currency' => 'usd']]])->assertOk();
        $this->assertSame(1, Transaction::where('stripe_invoice_id', 'in_renew')->count());

        $send(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_w', 'mode' => 'payment', 'payment_status' => 'paid', 'client_reference_id' => (string) $u->id, 'amount_total' => 2500, 'currency' => 'usd', 'metadata' => ['type' => 'export_pass']]]])->assertOk();
        $this->assertSame(3, $u->availableExportPassCredits());

        $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad'], '{}')->assertStatus(400);
    }

    public function test_the_admin_dashboard_shows_the_stats_and_the_latest_ten(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.test']);
        $users = User::factory()->count(12)->create();
        Subscription::create(['user_id' => $users[0]->id, 'plan_id' => Plan::where('plan_key', 'starter')->value('id'), 'status' => 'active', 'started_at' => now()]);
        Subscription::create(['user_id' => $users[1]->id, 'plan_id' => Plan::where('plan_key', 'agency')->value('id'), 'status' => 'active', 'started_at' => now()]);
        Subscription::create(['user_id' => $users[2]->id, 'plan_id' => Plan::where('plan_key', 'free')->value('id'), 'status' => 'active', 'started_at' => now()]);
        foreach (range(1, 12) as $i) {
            AnalysisRun::create(['public_id' => "p$i", 'user_id' => $users[0]->id, 'submitted_url' => "https://site$i.example", 'normalized_url' => "https://site$i.example", 'idempotency_key' => "k$i", 'status' => 'complete', 'current_stage' => 'done', 'source_mode' => 'live']);
        }
        SiteWatch::create(['user_id' => $users[0]->id, 'submitted_url' => 'https://site1.example', 'normalized_url' => 'https://site1.example', 'frequency' => 'weekly', 'is_enabled' => true]);

        $r = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $r->assertSeeInOrder(['Total users', '12', 'Website analyses', '12', 'Websites watched', '1', 'Subscriptions', '2', 'MRR (USD)', '$128.00']);
        $r->assertSee('Latest 10 sign-ups')->assertSee('Latest 10 websites entered');
        $r->assertSee('site12.example')->assertDontSee('site2.example</a>')->assertDontSee($users[0]->email === 'x' ? 'x' : 'site1.example</a>');
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_the_admin_can_add_edit_and_deactivate_users_and_a_deactivated_user_is_signed_out(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'New Person', 'email' => 'new@example.com', 'role' => 'user', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1'])->assertRedirect(route('admin.users.index'));
        $new = User::where('email', 'new@example.com')->first();
        $this->actingAs($admin)->put(route('admin.users.update', $new), ['name' => 'Renamed', 'email' => 'new@example.com', 'role' => 'user'])->assertRedirect();
        $this->assertSame('Renamed', $new->fresh()->name);

        $this->actingAs($new)->get(route('dashboard'))->assertOk();
        $this->actingAs($admin)->post(route('admin.users.toggle-active', $new));
        $this->assertFalse($new->fresh()->is_active);
        $this->actingAs($new->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post('/logout');
        $this->post(route('login'), ['email' => 'new@example.com', 'password' => 'secret-pass-1'])->assertSessionHasErrors('email');
    }

    public function test_visits_are_recorded_with_place_device_and_source_and_shown_in_analytics(): void
    {
        config(['redo.geoip_enabled' => true]);
        Http::fake(['ipwho.is/*' => Http::response(['success' => true, 'country' => 'Malaysia', 'city' => 'Kuala Lumpur'])]);
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => $iphone, 'HTTP_REFERER' => 'https://www.google.com/search?q=x'])->get('/pricing')->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'Googlebot/2.1'])->get('/pricing');

        $this->assertSame(1, PageView::count(), 'bots are not visitors');
        $v = PageView::first();
        $this->assertSame(['Malaysia', 'Kuala Lumpur', 'mobile', 'google.com'], [$v->country, $v->city, $v->device, $v->source]);
        $this->assertArrayNotHasKey('ip', $v->getAttributes());

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.analytics'))->assertOk()->assertSee('Kuala Lumpur')->assertSee('Malaysia')->assertSee('Mobile')->assertSee('google.com');
        $this->actingAs($admin)->get(route('admin.analytics', ['device' => 'desktop']))->assertOk()->assertDontSee('Kuala Lumpur');
        $this->actingAs(User::factory()->create())->get(route('admin.analytics'))->assertForbidden();
    }

    public function test_device_and_source_classification(): void
    {
        $this->assertSame('desktop', VisitClassifier::device('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120'));
        $this->assertSame('tablet', VisitClassifier::device('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)'));
        $this->assertSame('mobile', VisitClassifier::device('Mozilla/5.0 (Linux; Android 14; Pixel 8) Mobile Safari'));
        $this->assertTrue(VisitClassifier::isBot('Mozilla/5.0 (compatible; bingbot/2.0)'));
        $this->assertFalse(VisitClassifier::isBot('Mozilla/5.0 (Macintosh) Safari/605'));
    }

    public function test_the_export_pass_launch_offer_is_charged_and_shown_with_the_regular_price_struck_through(): void
    {
        config(['services.stripe.export_offer_price' => 'price_offer10']);
        $u = User::factory()->create();
        $this->onFree($u);
        $run = \App\Models\AnalysisRun::create(['public_id' => 'up1', 'user_id' => $u->id, 'submitted_url' => 'https://shop.example', 'normalized_url' => 'https://shop.example/', 'idempotency_key' => 'k', 'status' => 'complete', 'current_stage' => 'ready', 'source_mode' => 'live']);

        $page = $this->actingAs($u)->get(route('analyses.upgrade', $run->public_id))->assertOk();
        $page->assertSee('Export Pass')->assertSee('Launch offer')->assertSee('<s class="text-faint decoration-2"', false)->assertSee('$25')->assertSee('$10')->assertSee('HTML, CSS &amp; JS', false);
        $this->assertStringContainsString(route('export-pass.purchase'), $page->getContent());

        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_9']),
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_o', 'url' => 'https://checkout.stripe.com/c/pay/cs_o']),
        ]);
        $this->actingAs($u)->post(route('export-pass.purchase'), ['run' => $run->public_id])->assertRedirect('https://checkout.stripe.com/c/pay/cs_o');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/checkout/sessions') && $r['mode'] === 'payment' && $r['line_items'][0]['price'] === 'price_offer10');

        // Without an offer price: regular price, nothing struck through.
        config(['services.stripe.export_offer_price' => '']);
        $plain = $this->actingAs($u)->get(route('analyses.upgrade', $run->public_id))->assertOk()->assertSee('$25')->assertDontSee('Launch offer')->assertDontSee('$10');
        $this->assertStringNotContainsString('<s class="text-faint', $plain->getContent());
    }
}
