<?php

namespace Tests\Feature;

use App\Jobs\RunSiteWatchScanJob;
use App\Models\AnalysisRun;
use App\Models\DiagnosisReport;
use App\Models\PageView;
use App\Models\Plan;
use App\Models\SiteWatch;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WatchReport;
use App\Models\WebsiteDna;
use App\Services\Orchestration\AnalyzeWebsiteAction;
use App\Services\Watch\WatchReportBuilder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WatchReportTest extends TestCase
{
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

    private function scan(User $u, array $dna, array $issues, bool $watchScan = true): AnalysisRun
    {
        $run = AnalysisRun::create(['public_id' => 'r'.uniqid(), 'user_id' => $u->id, 'submitted_url' => 'https://shop.example', 'normalized_url' => 'https://shop.example', 'idempotency_key' => uniqid(), 'status' => 'complete', 'current_stage' => 'ready', 'source_mode' => 'live', 'is_watch_scan' => $watchScan]);
        WebsiteDna::create(['analysis_run_id' => $run->id, 'version' => 1, 'dna_json' => $dna, 'model_identifier' => 't', 'prompt_version' => 'v1']);
        DiagnosisReport::create(['analysis_run_id' => $run->id, 'version' => 1, 'summary' => 's', 'issues_json' => ['issues' => $issues], 'model_identifier' => 't', 'prompt_version' => 'v1', 'created_at' => now()]);

        return $run;
    }

    private function dna(string $vp, array $ctas, array $offers = ['Cakes']): array
    {
        return ['business_name' => 'Shop', 'value_proposition' => ['text' => $vp], 'cta_inventory' => array_map(fn ($l) => ['label' => $l], $ctas), 'offerings' => array_map(fn ($n) => ['name' => $n], $offers), 'trust_signals' => []];
    }

    private function issue(string $title, string $sev = 'medium', string $cat = 'conversion'): array
    {
        return ['issue_id' => 'I-'.crc32($title), 'title' => $title, 'severity' => $sev, 'category' => $cat, 'recommended_solution' => "Fix: {$title}"];
    }

    private function watch(User $u, int $days = 7): SiteWatch
    {
        return SiteWatch::create(['user_id' => $u->id, 'submitted_url' => 'https://shop.example', 'normalized_url' => 'https://shop.example', 'frequency_days' => $days, 'is_enabled' => true, 'next_scan_at' => now()]);
    }

    public function test_the_first_scan_is_a_baseline_and_the_next_shows_exactly_what_changed(): void
    {
        $u = $this->userOn('starter');
        $w = $this->watch($u);
        $a = $this->scan($u, $this->dna('We bake cakes for every celebration in town', ['Order now', 'Menu']), [$this->issue('Weak headline on the homepage', 'high', 'positioning'), $this->issue('No phone number visible near the order button')]);
        $base = app(WatchReportBuilder::class)->build($w, $a, null);
        $this->assertSame('baseline', $base->trend);
        $this->assertStringContainsString('2 things to improve', $base->headline);
        $this->assertNotEmpty($base->summary_json['actions']);

        $b = $this->scan($u, $this->dna('Wedding and birthday cakes baked fresh to order, delivered across the city', ['Order now', 'Get a quote'], ['Cakes', 'Cupcakes']), [$this->issue('No phone number is visible near the order button'), $this->issue('Page loads slowly on mobile', 'high', 'performance')]);
        $r = app(WatchReportBuilder::class)->build($w, $b, $a);
        $s = $r->summary_json;

        $this->assertFalse($s['baseline']);
        $this->assertSame(['Get a quote'], $s['changes']['ctas']['added']);
        $this->assertSame(['Menu'], $s['changes']['ctas']['removed']);
        $this->assertSame(['Cupcakes'], $s['changes']['offerings']['added']);
        $this->assertArrayHasKey('value_proposition', $s['changes']);
        $this->assertSame(['Page loads slowly on mobile'], array_column($s['issues']['new'], 'title'));
        $this->assertSame(['Weak headline on the homepage'], array_column($s['issues']['resolved'], 'title'), 'a reworded finding is the same issue, not a new one');
        $this->assertSame(1, $s['issues']['high_new']);
        $this->assertSame('attention', $r->trend);
        $this->assertSame('Page loads slowly on mobile', $s['actions'][0]['title'], 'new and serious comes first');
    }

    public function test_when_things_get_fixed_the_trend_improves_and_visits_to_the_published_copy_are_counted(): void
    {
        $u = $this->userOn('agency');
        $w = $this->watch($u);
        $a = $this->scan($u, $this->dna('We bake cakes', ['Order']), [$this->issue('Slow mobile pages', 'high', 'performance'), $this->issue('Missing contact details', 'medium', 'trust')]);
        $b = $this->scan($u, $this->dna('We bake cakes', ['Order']), [$this->issue('Missing contact details', 'medium', 'trust')]);
        $mine = $this->scan($u, $this->dna('x', ['y']), [], false);
        $mine->update(['published_slug' => 'shop-redo']);
        foreach ([1, 2, 3] as $i) {
            PageView::create(['path' => 'sites/shop-redo', 'visitor_hash' => 'v'.($i % 2), 'created_at' => now()->subDays(2)]);
        }
        PageView::create(['path' => 'sites/other', 'visitor_hash' => 'z', 'created_at' => now()->subDay()]);

        $r = app(WatchReportBuilder::class)->build($w, $b, $a);
        $this->assertSame('improved', $r->trend);
        $this->assertSame(['published' => true, 'slug' => 'shop-redo', 'days' => 7, 'views' => 3, 'visitors' => 2, 'previous_views' => 0], $r->summary_json['visits']);
    }

    public function test_the_job_rescans_without_touching_the_quota_and_records_the_report_or_the_failure(): void
    {
        $u = $this->userOn('starter');
        $w = $this->watch($u);
        $prev = $this->scan($u, $this->dna('We bake cakes', ['Order']), [$this->issue('Slow mobile pages', 'high', 'performance')]);
        $w->update(['last_analysis_run_id' => $prev->id]);
        $before = $u->analysesUsed();

        $this->mock(AnalyzeWebsiteAction::class, function ($m) {
            $m->shouldReceive('execute')->once()->andReturnUsing(function (AnalysisRun $run) {
                WebsiteDna::create(['analysis_run_id' => $run->id, 'version' => 1, 'dna_json' => $this->dna('We bake cakes', ['Order', 'Call us']), 'model_identifier' => 't', 'prompt_version' => 'v1']);
                DiagnosisReport::create(['analysis_run_id' => $run->id, 'version' => 1, 'summary' => 's', 'issues_json' => ['issues' => []], 'model_identifier' => 't', 'prompt_version' => 'v1', 'created_at' => now()]);
                $run->update(['status' => 'complete']);
            });
        });
        RunSiteWatchScanJob::dispatchSync($w->id);

        $w = $w->fresh();
        $this->assertSame('ok', $w->last_status);
        $report = $w->latestReport;
        $this->assertSame('improved', $report->trend);
        $this->assertSame(['Call us'], $report->summary_json['changes']['ctas']['added']);
        $this->assertTrue($w->lastAnalysisRun->is_watch_scan);
        $this->assertSame($before, $u->analysesUsed(), 'scheduled scans never use up the plan’s analyses');

        $this->mock(AnalyzeWebsiteAction::class, fn ($m) => $m->shouldReceive('execute')->once()->andThrow(new \RuntimeException('boom')));
        RunSiteWatchScanJob::dispatchSync($w->id);
        $this->assertSame('failed', $w->fresh()->last_status);
        $this->assertSame('failed', WatchReport::where('site_watch_id', $w->id)->latest('id')->first()->status);
    }

    public function test_the_hourly_command_queues_only_due_watches_of_paid_plans_and_moves_the_clock(): void
    {
        Queue::fake();
        $starter = $this->userOn('starter');
        $due = $this->watch($starter, 30);
        $later = $this->watch($starter, 30);
        $later->update(['next_scan_at' => now()->addDays(3)]);
        $paused = $this->watch($starter, 30);
        $paused->update(['is_enabled' => false]);
        $free = $this->watch($this->userOn('free'), 30);

        $this->artisan('redo:run-site-watches')->assertSuccessful();

        Queue::assertPushed(RunSiteWatchScanJob::class, 1);
        Queue::assertPushed(RunSiteWatchScanJob::class, fn ($j) => $j->watchId === $due->id);
        $this->assertTrue($due->fresh()->next_scan_at->between(now()->addDays(29), now()->addDays(31)));
        $this->artisan('redo:run-site-watches')->assertSuccessful();
        Queue::assertPushed(RunSiteWatchScanJob::class, 1); // an hourly tick never queues it twice
    }

    public function test_people_choose_weekly_or_monthly_and_can_change_it_or_scan_now(): void
    {
        Queue::fake();
        $u = $this->userOn('starter');
        $this->actingAs($u)->post(route('site-watches.store'), ['url' => 'https://example.com', 'frequency' => 'weekly'])->assertRedirect();
        $w = SiteWatch::firstOrFail();
        $this->assertSame(7, $w->frequency_days);

        $this->actingAs($u)->patch(route('site-watches.update', $w), ['frequency' => 'monthly'])->assertSessionHas('status');
        $this->assertSame(30, $w->fresh()->frequency_days);
        $this->actingAs($u)->patch(route('site-watches.update', $w), ['frequency' => 'daily'])->assertSessionHasErrors('frequency');

        $this->actingAs($u)->post(route('site-watches.scan', $w))->assertSessionHas('status');
        Queue::assertPushed(RunSiteWatchScanJob::class);
        $this->actingAs(User::factory()->create())->post(route('site-watches.scan', $w))->assertForbidden();

        $free = $this->userOn('free');
        $this->actingAs($free)->post(route('site-watches.store'), ['url' => 'https://example.org', 'frequency' => 'weekly']);
        $this->assertSame(1, SiteWatch::count(), 'Free plans can’t add watches');
    }

    public function test_the_dashboard_shows_watches_above_recent_analyses_with_the_report_for_paid_plans_only(): void
    {
        $u = $this->userOn('starter');
        $w = $this->watch($u);
        $a = $this->scan($u, $this->dna('We bake cakes', ['Order', 'Menu']), [$this->issue('Slow mobile pages', 'high', 'performance')]);
        $b = $this->scan($u, $this->dna('We bake cakes', ['Order', 'Quote']), [$this->issue('Missing contact details', 'medium', 'trust')]);
        $r = app(WatchReportBuilder::class)->build($w, $b, $a);
        $w->update(['last_status' => 'ok', 'last_scanned_at' => now(), 'last_analysis_run_id' => $b->id]);

        $html = $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Self-evolving watches')->assertSee('Automatically rescans a website on a schedule and updates its diagnosis')
            ->assertSee($r->headline)->assertSee('What changed on your page')->assertSee('Do this next')->assertSee('Once a week')->assertSee('Scan now')->getContent();
        $this->assertLessThan(strpos($html, 'Recent analyses'), strpos($html, 'Self-evolving watches'), 'watches come first');

        $this->actingAs($this->userOn('free'))->get(route('dashboard'))->assertOk()->assertSee('Watch reports are on the Starter and Agency plans')->assertDontSee('Watch this website');
    }
}
