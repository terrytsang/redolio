<?php

namespace App\Services\Hero;

use App\Models\AnalysisRun;
use App\Models\User;
use App\Services\Editing\SpecEditException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Sharper hero wording" on the first REDO: after the first design is generated, read the site's positioning, write the
 * hero options, and put the RECOMMENDED one on the page — so the first thing a visitor sees is already the sharper
 * wording, not something they have to ask for in the editor.
 *
 * Only an option that passed every hard factual gate AND is rated "recommended" is ever applied. Anything less (no
 * recommended option, model unavailable, nothing grounded) leaves the generated page as it was and the options for the
 * owner to choose from; a failure here never costs the redesign.
 */
class AutoHeroService
{
    public function __construct(
        private readonly PositioningIntelligenceService $positioning,
        private readonly HeroCopyGenerationService $copy,
        private readonly HeroCopyVersionService $versions,
    ) {}

    /** @return array{status: string, option_id?: int, spec_version?: int, reason?: string} */
    public function apply(AnalysisRun $run, ?User $user = null): array
    {
        if (! config('hero_layout.auto_apply', true)) {
            return ['status' => 'disabled'];
        }
        $spec = $run->currentDesignSpec();
        $json = $spec?->design_spec_json ?? [];
        if (! isset($json['v15'])) {
            return ['status' => 'skipped', 'reason' => 'not a layout-based design'];
        }
        if (! empty($json['v16']['hero_content'])) {
            return ['status' => 'skipped', 'reason' => 'hero wording already chosen'];
        }

        try {
            $profile = $run->currentPositioningProfile();
            if (! $profile || ! in_array($profile->status, ['ready', 'needs_confirmation'], true)) {
                $this->positioning->build($run);
            }
            $variant = collect($json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero')['variant_id'] ?? null;
            $options = $this->copy->generate($run, variantId: $variant);
            $pick = $options->first(fn ($o) => $o->status === 'candidate' && $o->hard_gate_status === 'pass' && $o->recommendation === 'recommended');
            if (! $pick) {
                return ['status' => 'no_recommended', 'reason' => 'no option was both fully supported by the site and strong enough to apply automatically'];
            }
            $r = $this->versions->approve($run, $pick, $user);

            return ['status' => 'applied', 'option_id' => $pick->id, 'spec_version' => $r['spec']->version];
        } catch (SpecEditException|Throwable $e) {
            Log::info('Automatic hero wording was not applied', ['run' => $run->public_id, 'reason' => $e->getMessage()]);

            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }
}
