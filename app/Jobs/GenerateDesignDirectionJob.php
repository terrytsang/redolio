<?php

namespace App\Jobs;

use App\Exceptions\SsrfViolationException;
use App\Models\AnalysisRun;
use App\Models\DesignDirection;
use App\Models\DesignDirectionGeneration;
use App\Services\Crawler\UrlSubmissionValidator;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\MetaApi\Validation\DesignDirectionCaptureValidator;
use App\Services\Orchestration\AnalyzeWebsiteAction;
use App\Services\Orchestration\ModelCallLogger;
use App\Services\DesignDirection\ReferenceScreenshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Admin "Design Intelligence" tool: crawls a reference website (reusing the
 * same SSRF-safe pipeline as a normal REDO scan) and asks the active AI
 * provider to capture a reusable Design Direction recipe from it. The
 * crawl/DNA run is tagged purpose=direction_capture and never attached to a
 * user, so it never appears in any dashboard or the admin scans list.
 */
class GenerateDesignDirectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    // Crawl + website analysis + the AI capture (each AI step takes 1-2 minutes) + the reference screenshot. Workers allow up to 900s.
    public int $timeout = 840;

    public int $tries = 1;

    public function __construct(public readonly int $generationId) {}

    /** Killed by the worker timeout, an unexpected error, or a lost job: never leave the admin's popup "capturing" forever. */
    public function failed(?Throwable $e = null): void
    {
        DesignDirectionGeneration::where('id', $this->generationId)->whereIn('status', ['pending', 'running'])->update([
            'status' => 'failed',
            'error_message' => 'The capture took too long or was interrupted. Please try again.',
        ]);
    }

    public function handle(
        UrlSubmissionValidator $urlValidator,
        AnalyzeWebsiteAction $analyzeWebsiteAction,
        MetaModelClient $metaClient,
        DesignDirectionCaptureValidator $captureValidator,
        ModelCallLogger $modelCallLogger,
        ReferenceScreenshot $screenshots,
    ): void {
        $generation = DesignDirectionGeneration::find($this->generationId);

        if (! $generation) {
            return;
        }

        $generation->update(['status' => 'running']);

        try {
            $normalized = $urlValidator->validateAndNormalize($generation->source_url);
        } catch (SsrfViolationException $e) {
            $generation->update(['status' => 'failed', 'error_message' => 'This website address could not be analyzed. Please check the URL and try again.']);

            return;
        }

        $run = AnalysisRun::create([
            'user_id' => null,
            'submitted_url' => $generation->source_url,
            'normalized_url' => $normalized,
            'status' => 'pending',
            'current_stage' => 'validating_url',
            'source_mode' => 'live',
            'purpose' => 'direction_capture',
        ]);

        $generation->update(['analysis_run_id' => $run->id]);

        $analyzeWebsiteAction->execute($run);
        $run->refresh();

        $dna = $run->currentWebsiteDna();

        if ($run->status !== 'complete' || ! $dna) {
            $generation->update([
                'status' => 'failed',
                'error_message' => $run->safe_error_message ?? 'Could not analyze this website. Please try a different URL.',
            ]);

            return;
        }

        $eligibleTemplateKeys = config('design_allowlist.templates');
        $context = ['run_id' => $run->public_id, 'requested_name' => $generation->requested_name];
        $requestHash = $metaClient->requestHash(['stage' => 'design_direction_capture', 'dna' => $dna->dna_json, 'context' => $context]);

        try {
            $response = $metaClient->generateDesignDirectionRecipe($dna->dna_json, $context);
            $modelCallLogger->logSuccess($run, 'design_direction_capture', $requestHash, $response);
        } catch (MetaApiException $e) {
            $modelCallLogger->logFailure($run, 'design_direction_capture', $requestHash, $e->errorCode);
            $generation->update(['status' => 'failed', 'error_message' => 'Redesign generation is temporarily unavailable. Please try again shortly.']);

            return;
        } catch (Throwable $e) {
            Log::error('Design direction capture failed', ['generation' => $generation->id, 'error' => $e->getMessage()]);
            $generation->update(['status' => 'failed', 'error_message' => 'Something went wrong while capturing this design direction. Please try again.']);

            return;
        }

        $validated = $captureValidator->validate($response->data, $eligibleTemplateKeys);

        // Admin left the name blank — name it from what was actually
        // captured (the AI's own short descriptive "family" phrase, e.g.
        // "Modern SaaS Infrastructure") rather than a generic placeholder.
        $finalName = trim($generation->requested_name) !== ''
            ? $generation->requested_name
            : Str::title($validated['family']);

        $directionKey = $this->uniqueDirectionKey(Str::slug($finalName, '_') ?: $generation->direction_key);

        // Real homepage screenshot of the reference site — shown alongside
        // the live-rendered template preview in the admin catalog and,
        // once selectable, on the user-facing direction picker, so there's
        // always a genuine image of what this direction was captured from,
        // not just an abstract description. Same zero-setup mshots service
        // already used for every original-site "BEFORE" screenshot.
        $referenceScreenshotUrl = $screenshots->capture($run->normalized_url, $directionKey);

        $direction = DesignDirection::create([
            'direction_key' => $directionKey,
            'name' => $finalName,
            'version' => 1,
            'is_selectable' => false,
            'created_at' => now(),
            'recipe_json' => [
                'direction_id' => $directionKey,
                'name' => $finalName,
                'family' => $validated['family'],
                'description' => $validated['description'],
                'best_for' => $validated['best_for'],
                'brand_traits' => $validated['brand_traits'],
                'color_system' => $validated['color_system'],
                'typography_system' => $validated['typography_system'],
                'layout_compositions' => $validated['layout_compositions'],
                'hero_patterns' => $validated['hero_patterns'],
                'section_patterns' => $validated['section_patterns'],
                'image_treatment' => $validated['image_treatment'],
                'spacing_system' => $validated['spacing_system'],
                'component_theme' => $validated['component_theme'],
                'responsive_rules' => $validated['responsive_rules'],
                'accessibility_rules' => $validated['accessibility_rules'],
                'preview_thumbnail' => $referenceScreenshotUrl,
                'version' => 1,
                'template_key' => $validated['template_key'],
                'source_url' => $generation->source_url,
            ],
        ]);

        $generation->update(['status' => 'complete', 'design_direction_id' => $direction->id, 'direction_key' => $directionKey]);
    }

    private function uniqueDirectionKey(string $base): string
    {
        $key = $base;
        $suffix = 1;

        while (DesignDirection::where('direction_key', $key)->exists()) {
            $suffix++;
            $key = $base.'_'.$suffix;
        }

        return $key;
    }
}
