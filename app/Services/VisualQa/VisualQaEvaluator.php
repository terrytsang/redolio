<?php

namespace App\Services\VisualQa;

use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\MetaApi\Validation\VisualQaScreenshotReviewValidator;
use App\Services\Publishing\ProductionArtifactGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * PRD v1.4 "Visual QA Worker": builds the same real, standalone artifact
 * the export/publish pipeline produces (never a screenshot mockup — the
 * PRD's AC-012/AC-015), captures desktop + mobile screenshots of it with
 * Playwright, runs deterministic checks, and — only when the active AI
 * provider actually supports image input — asks it to review the same
 * screenshots for issues that can't be measured deterministically
 * (hierarchy, overlap, readability). The deterministic layer always runs
 * and is always trustworthy on its own; the AI layer is strictly additive
 * and skips silently (never fails the report) when unavailable, so this
 * never claims an unverified capability.
 */
class VisualQaEvaluator
{
    public function __construct(
        private readonly ProductionArtifactGenerator $artifactGenerator,
        private readonly BrowserService $browser,
        private readonly MetaModelClient $metaClient,
        private readonly VisualQaScreenshotReviewValidator $visionValidator,
    ) {}

    /**
     * @return array{findings: array, viewports: array, screenshot_urls: array<string, string>, ai_review: ?array}
     */
    public function evaluate(AnalysisRun $run, DesignSpec $spec): array
    {
        $workDir = storage_path('app/private/visual-qa-tmp/'.Str::uuid());

        try {
            $this->artifactGenerator->generate($run, $spec, $workDir);

            $expectations = $this->blueprintExpectations($spec);

            $viewports = $this->browser->capture(
                "{$workDir}/index.html",
                "{$workDir}/screenshots",
                $expectations,
            );

            $screenshotUrls = $this->publishScreenshots($run, $spec, $viewports);
            $findings = array_merge($this->buildFindings($viewports), $expectations ? $this->buildBlueprintFindings($viewports) : []);
            [$aiFindings, $aiAssessment] = $this->runAiReview($run, $spec, $viewports, $findings);

            return [
                'findings' => array_merge($findings, $aiFindings),
                'viewports' => array_map(fn ($v) => array_diff_key($v, ['screenshot_path' => true]), $viewports),
                'screenshot_urls' => $screenshotUrls,
                'ai_review' => $aiAssessment,
                'provider' => $this->browser->id(),
                'blueprint' => $expectations ? [
                    'blueprint_id' => $spec->design_spec_json['v15']['design']['blueprint_id'],
                    'direction_id' => $spec->design_spec_json['v15']['design']['direction_id'],
                    'content_mapping_status' => $spec->design_spec_json['v15']['content_mapping']['status'],
                ] : null,
            ];
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * @return array{0: array, 1: ?string} [ai findings, overall assessment or null if skipped]
     */
    private function runAiReview(AnalysisRun $run, DesignSpec $spec, array $viewports, array $deterministicFindings): array
    {
        $images = [];
        foreach ($viewports as $v) {
            if ($v['screenshot_path'] && File::exists($v['screenshot_path'])) {
                $images[$v['viewport']] = File::get($v['screenshot_path']);
            }
        }

        if (empty($images)) {
            return [[], null];
        }

        $designSpecSummary = [
            'template_key' => $spec->template_key,
            'business_name' => $spec->design_spec_json['business_name'] ?? null,
            'hero' => $spec->design_spec_json['hero'] ?? null,
        ];

        try {
            $response = $this->metaClient->evaluateScreenshots($images, $designSpecSummary, [
                'run_id' => $run->public_id,
                'deterministic_findings' => $deterministicFindings,
            ]);
        } catch (MetaApiException $e) {
            Log::info('Visual QA AI review skipped', ['spec' => $spec->id, 'reason' => $e->getMessage()]);

            return [[], null];
        } catch (Throwable $e) {
            Log::warning('Visual QA AI review failed unexpectedly', ['spec' => $spec->id, 'error' => $e->getMessage()]);

            return [[], null];
        }

        $validated = $this->visionValidator->validate($response->data);

        return [$validated['findings'], $validated['overall_assessment']];
    }

    /**
     * @return array<string, string> viewport => public URL
     */
    private function publishScreenshots(AnalysisRun $run, DesignSpec $spec, array $viewports): array
    {
        $urls = [];

        foreach ($viewports as $viewport) {
            if (! $viewport['screenshot_path'] || ! File::exists($viewport['screenshot_path'])) {
                continue;
            }

            $relativePath = "visual-qa/{$run->public_id}/v{$spec->version}-{$viewport['viewport']}.png";
            Storage::disk('public')->put($relativePath, File::get($viewport['screenshot_path']));
            $urls[$viewport['viewport']] = Storage::disk('public')->url($relativePath);
        }

        return $urls;
    }

    /**
     * Maps the PRD's visual_qa.checks checklist to the deterministic signals
     * capture.cjs can actually measure in-browser, with PRD severities.
     *
     * @return array<int, array{check: string, viewport: string, severity: string, status: string, evidence: string}>
     */
    private function buildFindings(array $viewports): array
    {
        $findings = [];

        foreach ($viewports as $v) {
            $viewport = $v['viewport'];

            if ($v['load_error']) {
                $findings[] = ['check' => 'Page loads without fatal browser errors', 'viewport' => $viewport, 'severity' => 'critical', 'status' => 'failed', 'evidence' => $v['load_error'], 'source' => 'deterministic'];

                continue;
            }

            $findings[] = [
                'check' => 'Page loads without fatal browser errors',
                'viewport' => $viewport,
                'severity' => 'critical',
                'status' => empty($v['page_errors']) ? 'passed' : 'failed',
                'evidence' => empty($v['page_errors']) ? 'No uncaught page errors.' : implode('; ', $v['page_errors']),
                'source' => 'deterministic',
            ];

            $findings[] = [
                'check' => 'No horizontal overflow',
                'viewport' => $viewport,
                'severity' => 'high',
                'status' => $v['has_horizontal_overflow'] ? 'failed' : 'passed',
                'evidence' => $v['has_horizontal_overflow']
                    ? "Document is wider than the {$v['width']}px viewport."
                    : "Fits within the {$v['width']}px viewport.",
                'source' => 'deterministic',
            ];

            $findings[] = [
                'check' => 'Images load',
                'viewport' => $viewport,
                'severity' => 'medium',
                'meta' => ['broken_srcs' => $v['broken_image_srcs'] ?? []],
                'status' => $v['broken_image_count'] > 0 ? 'failed' : 'passed',
                'evidence' => $v['broken_image_count'] > 0
                    ? "{$v['broken_image_count']} of {$v['image_count']} image(s) failed to load."
                    : "All {$v['image_count']} image(s) loaded.",
                'source' => 'deterministic',
            ];

            if (! empty($v['console_errors'])) {
                $findings[] = [
                    'check' => 'No console errors',
                    'viewport' => $viewport,
                    'severity' => 'low',
                    'status' => 'failed',
                    'evidence' => implode('; ', array_slice($v['console_errors'], 0, 3)),
                    'source' => 'deterministic',
                ];
            }
        }

        return $findings;
    }

    /**
     * What a blueprint site must look like once rendered: the section sequence from
     * the validated v1.5 spec and every visible string it was allowed to contain
     * (so numbers/prices that are NOT in the spec can be flagged as invented).
     */
    private function blueprintExpectations(DesignSpec $spec): ?array
    {
        $v15 = $spec->design_spec_json['v15'] ?? null;
        if (! is_array($v15)) {
            return null;
        }

        $text = [$v15['project']['business_name'], $v15['pages'][0]['page_title'], (string) $v15['pages'][0]['meta_description']];
        array_walk_recursive($v15['pages'][0]['sections'], function ($v, $k) use (&$text) {
            is_string($v) && ! in_array($k, ['href', 'asset_id', 'type', 'name', 'form_action'], true) && $text[] = $v;
        });

        return [
            'sections' => array_column($v15['pages'][0]['sections'], 'instance_id'),
            'allowed_text' => mb_strtolower(implode(' ', $text)),
        ];
    }

    /**
     * PRD v1.5 blueprint QA checks, from the in-page inspection every browser provider returns.
     *
     * @return array<int, array>
     */
    private function buildBlueprintFindings(array $viewports): array
    {
        $out = [];
        $add = function (string $check, string $viewport, string $severity, bool $failed, string $pass, string $fail, array $meta = []) use (&$out) {
            $out[] = ['check' => $check, 'viewport' => $viewport, 'severity' => $severity, 'status' => $failed ? 'failed' : 'passed', 'evidence' => $failed ? $fail : $pass, 'source' => 'deterministic', 'meta' => $meta];
        };

        foreach ($viewports as $v) {
            $b = $v['blueprint'] ?? null;
            $name = $v['viewport'];
            if ($v['load_error'] ?? null) {
                continue; // already reported as a failed page load
            }
            // A QA that cannot run its checks must never read as a pass.
            if (! is_array($b) || isset($b['error'])) {
                $add('Blueprint checks ran', $name, 'critical', true, '', 'The blueprint inspection did not run: '.(is_array($b) ? $b['error'] : 'no result returned').'.');

                continue;
            }

            $add('Rendered sections match the selected blueprint', $name, 'high', ! $b['sequence_matches'] || $b['duplicate_sections'],
                count($b['section_ids']).' sections in the blueprint order.',
                'Expected ['.implode(', ', $b['expected_section_ids']).'] but rendered ['.implode(', ', $b['section_ids']).'].');
            $add('No empty or missing sections', $name, 'high', $b['empty_sections'] !== [], 'Every section has visible content.',
                'Empty section(s): '.implode(', ', $b['empty_sections']).'.', ['instance_ids' => $b['empty_sections']]);
            $add('Internal links resolve', $name, 'high', $b['broken_links'] !== [], 'Every in-page link has a target.',
                'Links with no target: '.implode(', ', $b['broken_links']).'.', ['hrefs' => $b['broken_links']]);
            $add($b['nav_mode'] === 'disclosure' ? 'Mobile navigation opens and lists links' : 'Desktop navigation is visible', $name, 'high', ! $b['nav_ok'],
                'Navigation works.', 'Navigation is missing, hidden or has no links.');
            $add('Primary call to action is visible', $name, 'high', ! $b['primary_cta_visible'], 'Hero call to action is visible.', 'The hero call to action is hidden or missing.');
            $add('Exactly one main heading', $name, 'medium', $b['h1_count'] !== 1, 'One h1.', "Found {$b['h1_count']} h1 elements.");

            if ($b['forms'] !== []) {
                $bad = array_filter($b['forms'], fn ($f) => $f['unlabeled'] > 0 || ! $f['has_target'] || ! $f['has_submit']);
                $add('Forms are labelled and have a submission target', $name, 'medium', $bad !== [], count($b['forms']).' form(s) are labelled and submit somewhere.', 'A form has an unlabelled field, no submit button or no submission target.');
            }
            if ($name === 'desktop') {
                $add('No unverified figures or claims', $name, 'high', $b['unverified_claims'] !== [], 'Every figure on the page comes from the verified spec.',
                    'Figures not found in the verified content: '.implode(', ', $b['unverified_claims']).'.', ['claims' => $b['unverified_claims']]);
                if ($v['reduced_motion_ok'] !== null) {
                    $add('Reduced-motion preference is respected', $name, 'low', $v['reduced_motion_ok'] === false, 'Transitions and animations are off under prefers-reduced-motion.', 'Motion is still active under prefers-reduced-motion.');
                }
            }
            if (! empty($v['failed_requests'])) {
                $add('No failed network requests', $name, 'medium', true, '', 'Failed: '.implode(', ', array_slice($v['failed_requests'], 0, 3)).'.');
            }
        }

        return $out;
    }
}
