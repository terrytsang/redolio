<?php

namespace App\Services\Hero;

use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Models\HeroRenderValidation;
use App\Services\Publishing\ProductionArtifactGenerator;
use App\Services\VisualQa\BrowserService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Measures the hero as it REALLY renders (browser, fonts loaded) at desktop, tablet and mobile, and judges it against
 * the hero variant's preferred line counts. It never passes what it could not measure: on a provider that cannot run
 * the measurement the result is `unavailable`; if fonts had not settled it is `exception`. It reads, it does not edit,
 * and it never changes type size to make copy fit.
 */
class HeroLayoutValidationService
{
    private const VIEWPORTS = ['desktop', 'tablet', 'mobile'];

    public function __construct(
        private readonly ProductionArtifactGenerator $artifacts,
        private readonly BrowserService $browser,
    ) {}

    /** @return array{result: string, viewports: array<string, array>, reasons: array<int, array{viewport: string, code: string, message: string}>, font_px: ?float, record: ?HeroRenderValidation} */
    public function validate(AnalysisRun $run, DesignSpec $spec, int $attempt = 0, ?int $optionId = null): array
    {
        $variant = $this->variantOf($spec);
        if ($this->browser->id() !== 'local' || ! $this->browser->isAvailable()) {
            // Measuring DOM layout needs the local browser worker. Never report "pass" for something we did not measure.
            return $this->finish($run, $spec, $attempt, $optionId, 'unavailable', [], [['viewport' => 'all', 'code' => 'measurement_unavailable', 'message' => 'Rendered-fit measurement needs the local browser worker; this environment cannot measure it, so fit is not claimed.']], null);
        }

        $work = storage_path('app/private/hero-fit-tmp/'.Str::uuid());
        try {
            $this->artifacts->generate($run, $spec, $work, includeProvenance: false);
            $proc = Process::timeout(120)->path(base_path())->run(['node', base_path('scripts/visual-qa/measure-hero.cjs'), "{$work}/index.html", "{$work}/shots"]);
            if (! $proc->successful()) {
                return $this->finish($run, $spec, $attempt, $optionId, 'exception', [], [['viewport' => 'all', 'code' => 'measurement_failed', 'message' => 'The browser measurement did not complete.']], null);
            }
            $measured = [];
            foreach (explode("\n", trim($proc->output())) as $line) {
                $d = json_decode($line, true);
                if (is_array($d) && isset($d['viewport'])) {
                    $measured[$d['viewport']] = $d;
                }
            }
            [$result, $reasons] = $this->judge($measured, $variant);
            $shots = $this->publishShots($run, $spec, $work, $measured);

            return $this->finish($run, $spec, $attempt, $optionId, $result, $measured, $reasons, $shots);
        } finally {
            File::deleteDirectory($work);
        }
    }

    /**
     * Pure judgement of measured values (unit-testable without a browser).
     *
     * @param  array<string, array>  $measured
     * @return array{0: string, 1: array<int, array{viewport: string, code: string, message: string}>}
     */
    public function judge(array $measured, ?string $variant): array
    {
        $reasons = [];
        $exception = false;
        foreach (self::VIEWPORTS as $vp) {
            $m = $measured[$vp] ?? null;
            if (! $m || ! empty($m['error']) || empty($m['found'])) {
                $exception = true;
                $reasons[] = ['viewport' => $vp, 'code' => 'not_measured', 'message' => "The {$vp} hero could not be measured."];

                continue;
            }
            if (empty($m['font_loaded'])) {
                $exception = true;
                $reasons[] = ['viewport' => $vp, 'code' => 'fonts_not_ready', 'message' => "Fonts had not finished loading at {$vp}, so line counts cannot be trusted."];

                continue;
            }
            $h1Max = HeroLayoutConstraints::preferredH1Lines($variant, $vp);
            $dMax = HeroLayoutConstraints::preferredDescriptionLines($variant, $vp);
            ($m['h1_lines'] ?? 0) > $h1Max && $reasons[] = ['viewport' => $vp, 'code' => 'h1_lines', 'message' => "The headline wraps to {$m['h1_lines']} lines at {$vp} (preferred: {$h1Max})."];
            ($m['description_lines'] ?? 0) > $dMax && $reasons[] = ['viewport' => $vp, 'code' => 'description_lines', 'message' => "The description wraps to {$m['description_lines']} lines at {$vp} (preferred: {$dMax})."];
            ! empty($m['overflow_detected']) && $reasons[] = ['viewport' => $vp, 'code' => 'overflow', 'message' => "Hero text overflows its container at {$vp}."];
            ! empty($m['clipping_detected']) && $reasons[] = ['viewport' => $vp, 'code' => 'clipping', 'message' => "Hero text is clipped at {$vp}."];
            empty($m['cta_visible']) && $reasons[] = ['viewport' => $vp, 'code' => 'cta_hidden', 'message' => "The main button is not visible at {$vp}."];
        }
        $fit = array_filter($reasons, fn ($r) => ! in_array($r['code'], ['not_measured', 'fonts_not_ready'], true));
        $result = $fit ? 'fail' : ($exception ? 'exception' : 'pass');

        return [$result, $reasons];
    }

    /** Per-viewport status for DesignSpec `hero_content.validation` (pass|fail|unavailable). */
    public static function statusByViewport(string $result, array $reasons): array
    {
        $out = [];
        foreach (self::VIEWPORTS as $vp) {
            $has = collect($reasons)->contains(fn ($r) => $r['viewport'] === $vp && ! in_array($r['code'], ['not_measured', 'fonts_not_ready', 'measurement_unavailable', 'measurement_failed'], true));
            $out["{$vp}_fit"] = match (true) {
                in_array($result, ['unavailable', 'exception'], true) => 'unavailable',
                $has => 'fail',
                default => 'pass',
            };
        }

        return $out;
    }

    public function variantOf(DesignSpec $spec): ?string
    {
        return collect($spec->design_spec_json['v15']['pages'][0]['sections'] ?? [])->firstWhere('component_id', 'hero')['variant_id'] ?? null;
    }

    private function finish(AnalysisRun $run, DesignSpec $spec, int $attempt, ?int $optionId, string $result, array $measured, array $reasons, ?array $shots): array
    {
        $worst = fn (string $k) => collect($measured)->max($k);
        $desktop = $measured['desktop'] ?? [];
        $record = HeroRenderValidation::create([
            'analysis_run_id' => $run->id, 'hero_copy_option_id' => $optionId, 'design_spec_id' => $spec->id, 'attempt' => $attempt,
            'viewport_json' => $measured, 'font_loaded' => $measured !== [] && collect($measured)->every(fn ($m) => ! empty($m['font_loaded'])),
            'headline_line_count' => $worst('h1_lines'), 'description_line_count' => $worst('description_lines'),
            'overflow_detected' => collect($measured)->contains(fn ($m) => ! empty($m['overflow_detected'])), 'clipping_detected' => collect($measured)->contains(fn ($m) => ! empty($m['clipping_detected'])),
            'cta_visible' => $measured !== [] && collect($measured)->every(fn ($m) => ! empty($m['cta_visible'])),
            'screenshot_path' => $shots['desktop'] ?? null, 'validation_result' => $result,
            'details_json' => ['reasons' => $reasons, 'screenshots' => $shots, 'font_px' => $desktop['h1_font_px'] ?? null, 'variant' => $this->variantOf($spec)], 'created_at' => now(),
        ]);

        return ['result' => $result, 'viewports' => $measured, 'reasons' => $reasons, 'font_px' => $desktop['h1_font_px'] ?? null, 'record' => $record, 'screenshots' => $shots ?? []];
    }

    /** @return array<string, string> viewport => public URL */
    private function publishShots(AnalysisRun $run, DesignSpec $spec, string $work, array $measured): array
    {
        $urls = [];
        foreach ($measured as $vp => $m) {
            $src = "{$work}/shots/".($m['screenshot'] ?? '');
            if (! empty($m['screenshot']) && File::exists($src)) {
                $rel = "hero-fit/{$run->public_id}/v{$spec->version}-".Str::random(6)."-{$vp}.png";
                Storage::disk('public')->put($rel, File::get($src));
                $urls[$vp] = Storage::disk('public')->url($rel);
            }
        }

        return $urls;
    }
}
