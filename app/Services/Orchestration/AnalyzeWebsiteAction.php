<?php

namespace App\Services\Orchestration;

use App\Exceptions\SsrfViolationException;
use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Models\DiagnosisReport;
use App\Models\SiteSnapshot;
use App\Models\WebsiteDna;
use App\Services\Assets\FallbackAssetService;
use App\Services\Assets\WebsiteAssetInventoryService;
use App\Services\Assets\WebsiteVisualProfileService;
use App\Services\Crawler\ContentQualityAssessor;
use App\Services\Crawler\SiteCrawler;
use App\Services\Crawler\SnapshotPersister;
use App\Services\Crawler\SourceCaptureService;
use App\Services\DesignDirection\DesignDirectionSelectorService;
use App\Services\MetaApi\EvidencePackFormatter;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\MetaApi\Validation\DiagnosisValidator;
use App\Services\MetaApi\Validation\WebsiteDnaValidator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the full P0 pipeline for one analysis run, synchronously and in
 * bounded stages (queues/cron availability on Opalstack is unverified —
 * see PRD FR-010), persisting after every stage so progress survives a
 * failure in a later stage.
 */
class AnalyzeWebsiteAction
{
    public function __construct(
        private readonly SiteCrawler $crawler,
        private readonly SnapshotPersister $snapshotPersister,
        private readonly MetaModelClient $metaClient,
        private readonly EvidencePackFormatter $evidencePackFormatter,
        private readonly WebsiteDnaValidator $dnaValidator,
        private readonly DiagnosisValidator $diagnosisValidator,
        private readonly GenerateDesignSpecAction $generateDesignSpecAction,
        private readonly StageLogger $stageLogger,
        private readonly ModelCallLogger $modelCallLogger,
        private readonly WebsiteAssetInventoryService $assetInventoryService,
        private readonly WebsiteVisualProfileService $visualProfileService,
        private readonly DesignDirectionSelectorService $designDirectionSelector,
        private readonly FallbackAssetService $fallbackAssetService,
        private readonly ContentQualityAssessor $contentQuality,
        private readonly SourceCaptureService $sourceCapture,
    ) {}

    public function execute(AnalysisRun $run): void
    {
        // This runs up to 5 sequential Meta API calls synchronously within
        // one HTTP request/response cycle (queues/cron availability on
        // Opalstack is unverified — see FR-010). PHP's default 30s
        // max_execution_time is nowhere near enough; a no-op in CLI/test
        // contexts where there's already no limit.
        set_time_limit(900);

        $run->update(['started_at' => now(), 'status' => 'running']);

        $snapshot = $this->runCrawlStage($run);
        if ($snapshot === null) {
            return;
        }

        $dna = $this->runDnaStage($run, $snapshot);
        if ($dna === null) {
            return;
        }

        // The admin's "Design Direction from a reference website" only needs to understand the site: no diagnosis, ranking or redesign
        // (three more AI calls that would only slow the capture down and are never shown to anyone).
        if ($run->purpose === 'direction_capture') {
            $run->update(['status' => 'complete', 'current_stage' => 'ready', 'completed_at' => now(),
                'duration_ms' => $run->started_at ? (int) round($run->started_at->diffInMilliseconds(now(), true)) : null]);

            return;
        }

        // Best-effort: a missing stock-photo fallback never blocks the
        // core pipeline — it only fills a gap when the site genuinely has
        // no usable hero/section imagery of its own.
        try {
            $this->fallbackAssetService->ensureHeroImagery($run, $dna);
        } catch (Throwable $e) {
            Log::warning('Fallback stock imagery failed', ['run' => $run->public_id, 'error' => $e->getMessage()]);
        }

        $diagnosis = $this->runDiagnosisStage($run, $snapshot, $dna);
        if ($diagnosis === null) {
            return;
        }

        // Best-effort: direction ranking never blocks the core pipeline.
        // The selector screen can always fall back to deterministic order.
        try {
            $this->designDirectionSelector->recommend($run);
        } catch (Throwable $e) {
            Log::warning('Design direction ranking failed', ['run' => $run->public_id, 'error' => $e->getMessage()]);
        }

        $designSpec = $this->runDesignSpecStage($run, $dna, $diagnosis);
        if ($designSpec === null) {
            return;
        }

        $run->update([
            'status' => 'complete',
            'current_stage' => 'ready',
            'completed_at' => now(),
            'duration_ms' => $run->started_at ? (int) round($run->started_at->diffInMilliseconds(now(), true)) : null,
        ]);

        app(AnalysisReadyNotifier::class)->send($run->fresh());
    }

    private function runCrawlStage(AnalysisRun $run): ?SiteSnapshot
    {
        $log = $this->stageLogger->start($run, 'crawling');

        try {
            // 1. Plain, SSRF-guarded crawl.
            $snapshotDto = null;
            $crawlError = null;
            try {
                $snapshotDto = $this->crawler->crawl($run->normalized_url);
            } catch (SsrfViolationException $e) {
                throw $e;
            } catch (Throwable $e) {
                $crawlError = $e; // a real browser may still succeed where a plain request failed
            }

            // 2. Is that actually the website? A bot challenge, an error page or an empty JavaScript shell is not content.
            $assessment = $snapshotDto ? $this->contentQuality->assess($snapshotDto) : ['ok' => false, 'reason' => 'unreachable', 'message' => 'The website could not be reached with a plain request.', 'signals' => []];

            // 3. A real browser: reads JavaScript / challenge-protected sites, and takes the genuine "before" screenshot.
            $capture = null;
            if ($this->sourceCapture->isAvailable()) {
                try {
                    $capture = $this->sourceCapture->capture($run->normalized_url);
                } catch (SsrfViolationException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    Log::warning('Source capture unavailable', ['run' => $run->public_id, 'error' => $e->getMessage()]);
                }
            }

            $readWithBrowser = false;
            if (! $assessment['ok'] && $capture && $capture->pages) {
                $rendered = $this->crawler->snapshotFromRendered(
                    $capture->pages,
                    $snapshotDto->homepageUrl ?? $run->normalized_url,
                    ['Read with a real browser because the plain request returned: '.$assessment['message']],
                );
                $second = $this->contentQuality->assess($rendered);
                if ($second['ok']) {
                    $snapshotDto = $rendered;
                    $assessment = $second;
                    $readWithBrowser = true;
                }
            }

            if ($snapshotDto === null && $crawlError !== null && ! $readWithBrowser) {
                throw $crawlError;
            }

            // 4. Never build a redesign from content we could not read.
            if (! $assessment['ok']) {
                if ($snapshotDto) {
                    $this->snapshotPersister->persist($run, $snapshotDto, $capture?->screenshotUrl);
                }
                $blocked = $assessment['reason'] === 'blocked';
                $this->stageLogger->fail(
                    $run, $log, $blocked ? 'content_blocked' : 'content_unreadable',
                    $blocked
                        ? 'REDOLIO could not read this website. It answered with an automated-access check (a bot-protection page) instead of its content, '
                            .($this->sourceCapture->isAvailable() ? 'and a real browser could not get past it either. ' : 'and browser-based reading is not enabled on this server. ')
                            .'No redesign was generated, because it would not reflect your real content. Allow REDOLIO\'s crawler in your site\'s protection settings, or try again.'
                        : 'REDOLIO could not read enough text from this website to analyse it (it may build its pages entirely with JavaScript or images). No redesign was generated, because it would not reflect your real content.'
                );
                Log::warning('Content gate stopped analysis', ['run' => $run->public_id, 'reason' => $assessment['reason'], 'signals' => $assessment['signals']]);

                return null;
            }

            $snapshot = $this->snapshotPersister->persist($run, $snapshotDto, $capture?->screenshotUrl);

            if (empty($snapshotDto->pages)) {
                $this->stageLogger->fail($run, $log, 'crawl_no_pages', 'The website could not be crawled. It may block automated access, require a browser to render, or be unreachable.');

                return null;
            }

            $warnings = $snapshotDto->warnings;
            if ($readWithBrowser) {
                $warnings[] = 'Content was read with a real browser.';
            }
            if (! empty($warnings)) {
                $this->stageLogger->partial($log, 'Crawl completed with warnings.', ['warnings' => $warnings, 'read_with_browser' => $readWithBrowser]);
            } else {
                $this->stageLogger->complete($log, null, ['page_count' => count($snapshotDto->pages)]);
            }

            // Best-effort: asset inventory never blocks the core DNA/diagnosis/
            // redesign pipeline, matching FR-004's screenshot-failure pattern.
            try {
                $this->assetInventoryService->build($run, $snapshotDto);
                $this->visualProfileService->build($run, $snapshotDto);
            } catch (Throwable $e) {
                Log::warning('Asset inventory or visual profile failed', ['run' => $run->public_id, 'error' => $e->getMessage()]);
            }

            return $snapshot;
        } catch (SsrfViolationException $e) {
            $this->stageLogger->fail($run, $log, 'url_blocked', 'This website could not be analyzed because its address is not a permitted public destination.');
            Log::warning('SSRF guard blocked crawl', ['run' => $run->public_id, 'reason' => $e->getMessage()]);

            return null;
        } catch (Throwable $e) {
            $this->stageLogger->fail($run, $log, 'crawl_failed', 'The website could not be reached. Please check the URL and try again.');
            Log::error('Crawl stage failed', ['run' => $run->public_id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function runDnaStage(AnalysisRun $run, SiteSnapshot $snapshot): ?WebsiteDna
    {
        $log = $this->stageLogger->start($run, 'building_dna');

        $evidencePack = $this->buildEvidencePack($snapshot);
        $validEvidenceIds = $snapshot->evidenceItems()->pluck('evidence_key')->all();
        $requestHash = hash('sha256', (string) json_encode(['stage' => 'website_understanding', 'evidence' => $evidencePack]));

        try {
            $response = $this->metaClient->generateWebsiteDna($evidencePack, ['run_id' => $run->public_id]);
            $this->modelCallLogger->logSuccess($run, 'website_understanding', $requestHash, $response);

            $validated = $this->dnaValidator->validate($response->data, $validEvidenceIds);

            $dna = WebsiteDna::create([
                'analysis_run_id' => $run->id,
                'version' => 1,
                'dna_json' => $validated,
                'model_identifier' => $response->modelIdentifier,
                'prompt_version' => config('meta_api.prompt_version'),
                'edited_by_user' => false,
            ]);

            $this->stageLogger->complete($log);

            return $dna;
        } catch (MetaApiException $e) {
            $this->modelCallLogger->logFailure($run, 'website_understanding', $requestHash, $e->errorCode);
            $this->stageLogger->fail($run, $log, $e->errorCode, 'Website understanding is temporarily unavailable. Try again shortly or load a demo example.');
            Log::error('DNA stage failed', ['run' => $run->public_id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function runDiagnosisStage(AnalysisRun $run, SiteSnapshot $snapshot, WebsiteDna $dna): ?DiagnosisReport
    {
        $log = $this->stageLogger->start($run, 'diagnosing');

        $evidencePack = $this->buildEvidencePack($snapshot);
        $validEvidenceIds = $snapshot->evidenceItems()->pluck('evidence_key')->all();
        $requestHash = hash('sha256', (string) json_encode(['stage' => 'diagnosis', 'dna' => $dna->dna_json]));

        try {
            $response = $this->metaClient->generateDiagnosis($evidencePack, $dna->dna_json, ['run_id' => $run->public_id]);
            $this->modelCallLogger->logSuccess($run, 'diagnosis', $requestHash, $response);

            $validated = $this->diagnosisValidator->validate($response->data, $validEvidenceIds);

            $diagnosis = DiagnosisReport::create([
                'analysis_run_id' => $run->id,
                'version' => 1,
                'summary' => $validated['summary'],
                'issues_json' => $validated,
                'model_identifier' => $response->modelIdentifier,
                'prompt_version' => config('meta_api.prompt_version'),
                'created_at' => now(),
            ]);

            $this->stageLogger->complete($log, null, ['issue_count' => count($validated['issues'])]);

            return $diagnosis;
        } catch (MetaApiException $e) {
            $this->modelCallLogger->logFailure($run, 'diagnosis', $requestHash, $e->errorCode);
            $this->stageLogger->fail($run, $log, $e->errorCode, 'Diagnosis is temporarily unavailable. Try again shortly or load a demo example.');

            return null;
        }
    }

    private function runDesignSpecStage(AnalysisRun $run, WebsiteDna $dna, DiagnosisReport $diagnosis): ?DesignSpec
    {
        $log = $this->stageLogger->start($run, 'preparing_redesign');

        try {
            $designSpec = $this->generateDesignSpecAction->execute($run, $dna, $diagnosis);
            $this->stageLogger->complete($log, null, ['version' => $designSpec->version]);

            return $designSpec;
        } catch (MetaApiException $e) {
            $this->stageLogger->fail($run, $log, $e->errorCode, 'Redesign generation is temporarily unavailable. Try again shortly or load a demo example.');

            return null;
        }
    }

    private function buildEvidencePack(SiteSnapshot $snapshot): array
    {
        return $this->evidencePackFormatter->build(
            $snapshot->snapshot_json,
            $snapshot->evidenceItems()->get()->map(fn ($e) => $e->toArray())->all()
        );
    }
}
