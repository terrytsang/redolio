<?php

namespace Tests\Feature;

use App\DTO\Crawler\SourceCapture;
use App\Exceptions\SsrfViolationException;
use App\Models\AnalysisRun;
use App\Models\SiteSnapshot;
use App\Services\Crawler\ContentQualityAssessor;
use App\Services\Crawler\HtmlPageExtractor;
use App\Services\Crawler\Security\IpRangeChecker;
use App\Services\Crawler\Security\SsrfGuard;
use App\Services\Crawler\SiteCrawler;
use App\Services\Crawler\SourceCaptureService;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\Orchestration\AnalyzeWebsiteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * A site behind a bot-protection challenge used to be "analysed" as its challenge page, producing a redesign
 * about nothing. These tests pin the behaviour that replaced it: detect, read with a real browser, or stop and say so.
 */
class SourceReadingTest extends TestCase
{
    use RefreshDatabase;

    private const POW = '<!doctype html><html><head><meta name="onehop-proof-of-work-id" content="abc"><title>POW</title><script type="module">function k(){}</script></head><body></body></html>';

    private function realPage(string $name = 'ANOZ GROUP OF COMPANIES'): string
    {
        return "<html><head><title>{$name}</title><meta name=\"description\" content=\"We Specialize In The Supply Of Raw Materials\"></head><body>"
            ."<nav><a href=\"/product\">Product</a><a href=\"/contact-us\">Contact Us</a></nav><h1>{$name}</h1><h2>We Specialize In The Supply Of Raw Materials</h2>"
            .'<p>In 2005, Anoz Group started its first operation as Union Aluminium Co. Ltd. in Suzhou, China, as a trading company for aluminium raw material products, and has since grown branches across China, Malaysia and Thailand.</p>'
            .'<a href="/product">Our Products</a></body></html>';
    }

    private function assess(string $html, int $status = 200): array
    {
        return app(ContentQualityAssessor::class)->assess(app(HtmlPageExtractor::class)->extract('https://site.example/', $status, $html));
    }

    public function test_a_proof_of_work_challenge_page_is_not_content(): void
    {
        $a = $this->assess(self::POW, 503);
        $this->assertFalse($a['ok']);
        $this->assertSame('blocked', $a['reason']);
        $this->assertContains('http_503', $a['signals']);
        $this->assertContains('challenge_title', $a['signals']);
        $this->assertContains('challenge_markup', $a['signals']);
    }

    public function test_other_bot_checks_and_error_pages_are_recognised(): void
    {
        foreach ([
            '<html><head><title>Just a moment...</title></head><body><p>Checking if the site connection is secure</p><script src="/cdn-cgi/challenge-platform/h/b"></script></body></html>',
            '<html><head><title>Attention Required! | Cloudflare</title></head><body><h1>Sorry, you have been blocked</h1></body></html>',
            '<html><head><title>Access Denied</title></head><body>You don\'t have permission to access this server.</body></html>',
            '<html><head><title>Welcome</title></head><body><p>Please verify you are human to continue. Complete the security check.</p></body></html>',
            '<html><head><title>Home</title><script src="https://geo.captcha-delivery.com/c.js"></script></head><body>x</body></html>',
        ] as $html) {
            $this->assertFalse($this->assess($html, 200)['ok'], substr($html, 0, 80));
        }
        // A rich page with an error status is still an error page.
        $this->assertSame('blocked', $this->assess($this->realPage(), 500)['reason']);
    }

    public function test_an_empty_javascript_shell_is_thin_but_a_real_page_passes(): void
    {
        $shell = '<html><head><title>App</title></head><body><div id="root"></div><script src="/app.js"></script></body></html>';
        $this->assertSame('thin', $this->assess($shell)['reason']);

        $ok = $this->assess($this->realPage());
        $this->assertTrue($ok['ok']);
        $this->assertNull($ok['reason']);
    }

    public function test_rendered_pages_become_a_snapshot_and_error_or_challenge_pages_are_dropped(): void
    {
        $snap = app(SiteCrawler::class)->snapshotFromRendered([
            ['url' => 'https://site.example/', 'status' => 200, 'html' => $this->realPage()],
            ['url' => 'https://site.example/blocked', 'status' => 200, 'html' => self::POW],
            ['url' => 'https://site.example/gone', 'status' => 404, 'html' => $this->realPage('Gone')],
        ], 'https://site.example/', ['Read with a real browser.']);

        $this->assertCount(1, $snap->pages);
        $this->assertSame('ANOZ GROUP OF COMPANIES', $snap->pages[0]->title);
        $this->assertNotEmpty($snap->evidence, 'evidence is built from the rendered content');
        $this->assertNotEmpty(array_filter($snap->warnings, fn ($w) => str_contains($w, '/blocked')));
        $this->assertNotEmpty(array_filter($snap->warnings, fn ($w) => str_contains($w, '/gone')));
        $this->assertTrue($this->assessSnapshot($snap)['ok']);
    }

    private function assessSnapshot($snap): array
    {
        return app(ContentQualityAssessor::class)->assess($snap);
    }

    // ------------------------------------------------------------------ the pipeline gate

    private function blockedSite(): void
    {
        Cache::flush();
        $this->app->instance(SsrfGuard::class, new SsrfGuard(new IpRangeChecker, fn () => ['93.184.216.34']));
        Http::fake([
            'https://shielded.example/robots.txt' => Http::response('', 404),
            'https://shielded.example/*' => Http::response(self::POW, 503),
        ]);
    }

    private function newRun(): AnalysisRun
    {
        return AnalysisRun::create(['public_id' => 'run_'.uniqid(), 'submitted_url' => 'https://shielded.example', 'normalized_url' => 'https://shielded.example/', 'idempotency_key' => uniqid(), 'status' => 'pending', 'current_stage' => 'validating_url', 'source_mode' => 'live']);
    }

    private function fakeCapture(bool $available, ?SourceCapture $result): void
    {
        $fake = Mockery::mock(SourceCaptureService::class);
        $fake->shouldReceive('isAvailable')->andReturn($available);
        $available && $fake->shouldReceive('capture')->andReturn($result);
        $this->app->instance(SourceCaptureService::class, $fake);
    }

    private function failingModel(): void
    {
        $model = Mockery::mock(MetaModelClient::class);
        $model->shouldReceive('generateWebsiteDna')->andThrow(new MetaApiException('stop here', 'test_stop'));
        $this->app->instance(MetaModelClient::class, $model);
    }

    public function test_a_challenge_protected_site_stops_with_a_clear_reason_and_never_reaches_the_model(): void
    {
        $this->blockedSite();
        $this->fakeCapture(false, null);
        $model = Mockery::mock(MetaModelClient::class);
        $model->shouldNotReceive('generateWebsiteDna');
        $this->app->instance(MetaModelClient::class, $model);

        $run = $this->newRun();
        app(AnalyzeWebsiteAction::class)->execute($run);
        $run->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertSame('content_blocked', $run->error_code);
        $this->assertStringContainsString('automated-access check', $run->safe_error_message);
        $this->assertStringContainsString('not enabled on this server', $run->safe_error_message);
        $this->assertStringContainsString('No redesign was generated', $run->safe_error_message);
        $this->assertNull($run->currentWebsiteDna());
        $this->assertNull($run->currentDesignSpec());
    }

    public function test_when_a_browser_cannot_clear_the_challenge_either_it_still_stops(): void
    {
        $this->blockedSite();
        $this->fakeCapture(true, new SourceCapture([['url' => 'https://shielded.example/', 'status' => 503, 'html' => self::POW, 'cleared' => false]], null, false));
        $model = Mockery::mock(MetaModelClient::class);
        $model->shouldNotReceive('generateWebsiteDna');
        $this->app->instance(MetaModelClient::class, $model);

        $run = $this->newRun();
        app(AnalyzeWebsiteAction::class)->execute($run);

        $this->assertSame('content_blocked', $run->fresh()->error_code);
        $this->assertStringContainsString('could not get past it either', $run->fresh()->safe_error_message);
    }

    public function test_a_browser_that_clears_the_challenge_supplies_the_real_content_and_the_real_screenshot(): void
    {
        $this->blockedSite();
        $shot = 'http://app.test/storage/site-captures/x/desktop.png';
        $this->fakeCapture(true, new SourceCapture([
            ['url' => 'https://shielded.example/', 'status' => 200, 'html' => $this->realPage(), 'cleared' => true],
            ['url' => 'https://shielded.example/product', 'status' => 200, 'html' => $this->realPage('Products'), 'cleared' => true],
        ], $shot, true));
        $this->failingModel(); // the pipeline proceeds past the crawl stage and stops at the (faked) model

        $run = $this->newRun();
        app(AnalyzeWebsiteAction::class)->execute($run);
        $run->refresh();

        $this->assertNotSame('content_blocked', $run->error_code, 'content was readable, so the gate let it through');
        $snapshot = $run->siteSnapshot;
        $this->assertNotNull($snapshot);
        $this->assertSame(2, $snapshot->page_count);
        $this->assertSame($shot, $snapshot->screenshot_path, 'the before image is OUR capture');
        $home = $snapshot->pages()->orderBy('id')->first();
        $this->assertSame('ANOZ GROUP OF COMPANIES', $home->title);
        $this->assertStringContainsString('Supply Of Raw Materials', $home->visible_text);
        $crawl = $run->stageLogs()->where('stage', 'crawling')->first();
        $this->assertSame('partial', $crawl->status);
        $this->assertTrue($crawl->metadata_json['read_with_browser']);
    }

    public function test_a_healthy_site_keeps_its_plain_content_and_still_gets_the_real_screenshot(): void
    {
        Cache::flush();
        $this->app->instance(SsrfGuard::class, new SsrfGuard(new IpRangeChecker, fn () => ['93.184.216.34']));
        Http::fake(['https://shielded.example/robots.txt' => Http::response('', 404), 'https://shielded.example/*' => Http::response($this->realPage('Plain Site'), 200, ['Content-Type' => 'text/html'])]);
        $shot = 'http://app.test/storage/site-captures/y/desktop.png';
        $this->fakeCapture(true, new SourceCapture([['url' => 'https://shielded.example/', 'status' => 200, 'html' => $this->realPage('Browser Copy'), 'cleared' => true]], $shot, true));
        $this->failingModel();

        $run = $this->newRun();
        app(AnalyzeWebsiteAction::class)->execute($run);

        $snapshot = $run->fresh()->siteSnapshot;
        $this->assertSame('Plain Site', $snapshot->pages()->orderBy('id')->first()->title, 'readable plain content is preferred');
        $this->assertSame($shot, $snapshot->screenshot_path);
    }

    // ------------------------------------------------------------------ SourceCaptureService

    public function test_the_capture_service_validates_the_url_stores_the_screenshot_and_cleans_up(): void
    {
        Storage::fake('public');
        config(['redo.source_capture.enabled' => true]);
        $guard = new SsrfGuard(new IpRangeChecker, fn () => ['93.184.216.34']);
        $workDir = null;
        Process::fake(function ($process) use (&$workDir) {
            $cfg = json_decode(end($process->command), true);
            $workDir = $cfg['outDir'];
            File::put($workDir.'/desktop.png', 'PNGDATA');

            return Process::result(json_encode(['ok' => true, 'pages' => [['url' => 'https://s.example/', 'status' => 200, 'html' => '<html>x</html>', 'cleared' => true]], 'screenshot' => 'desktop.png', 'challenge_cleared' => false, 'errors' => ['e'], 'blocked' => ['10.0.0.1']])."\n");
        });

        $capture = (new SourceCaptureService($guard))->capture('https://s.example/');

        $this->assertCount(1, $capture->pages);
        $this->assertFalse($capture->challengeCleared);
        $this->assertSame(['10.0.0.1'], $capture->blocked);
        $this->assertMatchesRegularExpression('#/storage/site-captures/[0-9a-f-]+/desktop\.png$#', $capture->screenshotUrl);
        $stored = Storage::disk('public')->allFiles('site-captures');
        $this->assertCount(1, $stored);
        $this->assertSame('PNGDATA', Storage::disk('public')->get($stored[0]));
        $this->assertNotNull($workDir);
        $this->assertDirectoryDoesNotExist($workDir, 'the work directory is removed');
    }

    public function test_the_capture_service_refuses_internal_urls_before_starting_a_browser_and_degrades_quietly_on_failure(): void
    {
        Process::fake();
        $internal = new SsrfGuard(new IpRangeChecker, fn () => ['10.0.0.5']);
        try {
            (new SourceCaptureService($internal))->capture('https://intranet.example/');
            $this->fail('Expected the internal address to be refused');
        } catch (SsrfViolationException) {
            Process::assertNothingRan();
        }

        $public = new SsrfGuard(new IpRangeChecker, fn () => ['93.184.216.34']);
        Process::fake(['*' => Process::result('', 'boom', 1)]);
        $this->assertNull((new SourceCaptureService($public))->capture('https://s.example/'), 'a failed browser is not fatal to the caller');
        Process::fake(['*' => Process::result('not json')]);
        $this->assertNull((new SourceCaptureService($public))->capture('https://s.example/'));
    }

    public function test_source_capture_is_off_unless_enabled(): void
    {
        config(['redo.source_capture.enabled' => false]);
        $this->assertFalse(app(SourceCaptureService::class)->isAvailable());
    }

    // ------------------------------------------------------------------ the before panel

    public function test_a_third_party_placeholder_screenshot_is_never_shown(): void
    {
        $this->assertNull((new SiteSnapshot(['screenshot_path' => 'https://s0.wp.com/mshots/v1/https%3A%2F%2Fanoz.co?w=1200']))->realScreenshotUrl());
        $this->assertNull((new SiteSnapshot(['screenshot_path' => null]))->realScreenshotUrl());
        $this->assertSame('http://app.test/storage/site-captures/a/desktop.png', (new SiteSnapshot(['screenshot_path' => 'http://app.test/storage/site-captures/a/desktop.png']))->realScreenshotUrl());
    }

    public function test_the_node_egress_proxy_never_lets_the_capture_browser_reach_internal_addresses(): void
    {
        if (! Process::run(['node', '-v'])->successful()) {
            $this->markTestSkipped('Node is not available.');
        }
        $run = Process::timeout(120)->path(base_path())->run(['node', '--test', 'scripts/visual-qa/egress-proxy.test.cjs']);
        $this->assertTrue($run->successful(), "egress proxy tests failed:\n".$run->output().$run->errorOutput());
        $this->assertStringContainsString('fail 0', $run->output());
    }
}
