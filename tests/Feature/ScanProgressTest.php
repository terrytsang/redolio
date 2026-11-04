<?php

namespace Tests\Feature;

use App\Mail\AnalysisReadyMail;
use App\Models\AnalysisRun;
use App\Models\AnalysisStageLog;
use App\Models\DesignSelection;
use App\Services\Orchestration\AnalysisProgress;
use App\Services\Orchestration\AnalysisReadyNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScanProgressTest extends TestCase
{
    use RefreshDatabase;

    private function scan(string $status = 'running', string $stage = 'crawling', array $extra = []): AnalysisRun
    {
        return AnalysisRun::create($extra + ['public_id' => (string) Str::uuid(), 'submitted_url' => 'https://a.test', 'normalized_url' => 'https://a.test/', 'idempotency_key' => uniqid(), 'status' => $status, 'current_stage' => $stage, 'source_mode' => 'live']);
    }

    private function log(AnalysisRun $r, string $stage, string $status, int $secondsAgoStart, ?int $secondsAgoEnd = null): void
    {
        AnalysisStageLog::create(['analysis_run_id' => $r->id, 'stage' => $stage, 'status' => $status, 'started_at' => now()->subSeconds($secondsAgoStart), 'completed_at' => $secondsAgoEnd !== null ? now()->subSeconds($secondsAgoEnd) : null]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_percent_grows_with_stages_never_hits_100_while_running_and_gives_a_time_left(): void
    {
        $r = $this->scan('running', 'crawling');
        $this->log($r, 'validating_url', 'complete', 10, 8);
        $this->log($r, 'crawling', 'running', 5);
        $early = app(AnalysisProgress::class)->snapshot($r);

        $this->assertGreaterThan(0, $early['percent']);
        $this->assertLessThan(100, $early['percent']);
        $this->assertGreaterThan(0, $early['eta_seconds']);
        $this->assertNotEmpty($early['eta_text']);

        foreach (['crawling', 'building_dna', 'diagnosing'] as $i => $s) {
            AnalysisStageLog::where('analysis_run_id', $r->id)->where('stage', $s)->delete();
            $this->log($r, $s, 'complete', 60, 30);
        }
        $r->update(['current_stage' => 'preparing_redesign']);
        $this->log($r, 'preparing_redesign', 'running', 9999); // a stage running far longer than estimated
        $late = app(AnalysisProgress::class)->snapshot($r->fresh());

        $this->assertGreaterThan($early['percent'], $late['percent']);
        $this->assertLessThanOrEqual(99, $late['percent']);   // never claims done early
        $this->assertGreaterThan(0, $late['eta_seconds']);    // never shows "0 left" while still working
        $this->assertSame('complete', $late['stages'][0]['state']);
        $this->assertSame('running', $late['stages'][4]['state']);
    }

    public function test_finished_scan_is_100_percent_and_has_no_eta(): void
    {
        $r = $this->scan('complete', 'ready');
        $s = app(AnalysisProgress::class)->snapshot($r);

        $this->assertSame(100, $s['percent']);
        $this->assertSame(0, $s['eta_seconds']);
        $this->assertNull($s['eta_text']);
    }

    public function test_estimates_use_real_stage_durations_once_there_is_history(): void
    {
        $r = $this->scan('running', 'crawling');
        foreach (range(1, 5) as $i) {
            $this->log($r, 'crawling', 'complete', 200, 100); // 100s each
        }

        $this->assertEqualsWithDelta(100, app(AnalysisProgress::class)->stageDurations()['crawling'], 1);
    }

    public function test_when_busy_a_waiting_scan_gets_its_position_and_a_longer_wait(): void
    {
        config(['redo.capacity.workers' => 1, 'redo.capacity.busy_threshold' => 3]);
        $this->scan('running', 'crawling');
        $this->scan('pending', 'validating_url');
        $mine = $this->scan('pending', 'validating_url');

        $s = app(AnalysisProgress::class)->snapshot($mine);

        $this->assertTrue($s['queue']['busy']);
        $this->assertTrue($s['queue']['waiting']);
        $this->assertSame(2, $s['queue']['position']);
        $this->assertGreaterThan(app(AnalysisProgress::class)->totalSeconds() * 0.5, $s['eta_seconds']); // queue wait is part of the estimate

        $idle = $this->scan('running', 'crawling', []);
        config(['redo.capacity.busy_threshold' => 99]);
        $this->assertFalse(app(AnalysisProgress::class)->snapshot($idle)['queue']['busy']);
    }

    public function test_admin_direction_capture_runs_do_not_count_as_user_load(): void
    {
        $cap = $this->scan('running', 'crawling');
        $cap->forceFill(['purpose' => 'direction_capture'])->save();

        $this->assertSame(0, app(AnalysisProgress::class)->inFlight()['running']);
    }

    public function test_the_progress_endpoint_returns_json_for_the_page_poller(): void
    {
        $r = $this->scan('running', 'building_dna');

        $this->getJson(route('analyses.progress', $r->public_id))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['percent', 'eta_seconds', 'eta_text', 'stages', 'queue' => ['busy', 'waiting', 'position'], 'signature']);
    }

    public function test_the_scan_page_shows_percent_and_time_left_and_the_busy_email_offer(): void
    {
        config(['redo.capacity.busy_threshold' => 1]);
        $r = $this->scan('running', 'crawling');

        $this->get(route('analyses.show', $r->public_id))
            ->assertOk()->assertSee('id="scan-percent"', false)->assertSee('left')->assertSee('Email me when ready')->assertSee('Lots of people are scanning');
    }

    public function test_email_opt_in_sends_exactly_once_when_the_scan_finishes(): void
    {
        Mail::fake();
        $r = $this->scan('running', 'crawling');

        $this->postJson(route('analyses.notify', $r->public_id), ['email' => 'visitor@example.com'])->assertOk();
        $this->assertSame('visitor@example.com', $r->fresh()->notify_email);
        Mail::assertNothingSent(); // not before it is ready

        $r->update(['status' => 'complete', 'current_stage' => 'ready']);
        $n = app(AnalysisReadyNotifier::class);
        $n->send($r->fresh());
        $n->send($r->fresh()); // a second finish signal must not double-send

        Mail::assertSent(AnalysisReadyMail::class, 1);
        Mail::assertSent(AnalysisReadyMail::class, fn ($m) => $m->hasTo('visitor@example.com') && str_contains($m->content()->with['url'], '/reveal'));
    }

    public function test_notify_on_a_finished_scan_sends_nothing_and_the_first_address_wins(): void
    {
        Mail::fake();
        $done = $this->scan('complete', 'ready');
        $busy = $this->scan('running', 'building_dna');

        $this->postJson(route('analyses.notify', $done->public_id), ['email' => 'victim@example.com'])->assertOk()->assertJsonPath('ready', true);
        $this->assertNull($done->fresh()->notify_email);
        Mail::assertNothingSent(); // the public link cannot make the app mail an arbitrary address

        $this->postJson(route('analyses.notify', $busy->public_id), ['email' => 'owner@example.com'])->assertOk();
        $this->postJson(route('analyses.notify', $busy->public_id), ['email' => 'attacker@example.com'])->assertOk();
        $this->assertSame('owner@example.com', $busy->fresh()->notify_email);
    }

    public function test_notify_rejects_a_bad_address(): void
    {
        $r = $this->scan();

        $this->postJson(route('analyses.notify', $r->public_id), ['email' => 'nope'])->assertStatus(422);
        $this->assertNull($r->fresh()->notify_email);
    }

    public function test_choosing_a_design_now_lands_on_the_reveal_not_the_preview(): void
    {
        $r = $this->scan('complete', 'ready');
        $spec = $r->designSpecs()->create(['version' => 1, 'template_key' => 'smb_default', 'design_spec_json' => ['sections' => []], 'status' => 'valid', 'model_identifier' => 't', 'prompt_version' => 'v']);
        $sel = DesignSelection::create(['analysis_run_id' => $r->id, 'direction_key' => 'x', 'status' => 'complete', 'design_spec_id' => $spec->id, 'preferences_json' => []]);

        $this->get(route('analyses.design-directions.generating', [$r->public_id, $sel->id]))->assertRedirect(route('analyses.reveal', $r->public_id));
    }
}
