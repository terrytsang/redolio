<?php

namespace App\Jobs;

use App\Models\DesignSelection;
use App\Models\User;
use App\Services\Blueprint\DesignSpecV15;
use App\Services\Hero\AutoHeroService;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\Orchestration\GenerateDesignSpecAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateDesignSpecJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 420; // design (≤ 180s) plus the automatic hero wording step

    public int $tries = 1;

    public function __construct(public readonly int $designSelectionId) {}

    /** A refinement of a blueprint-generated spec keeps its layout (same direction only). */
    private function inheritedBlueprintId(DesignSelection $selection): ?string
    {
        $previous = $selection->refineFromDesignSpec?->design_spec_json['v15']['design'] ?? null;

        return $previous && $previous['direction_id'] === $selection->direction_key ? $previous['blueprint_id'] : null;
    }

    public function handle(GenerateDesignSpecAction $generateDesignSpecAction, DesignSpecV15 $designSpecV15, ?AutoHeroService $autoHero = null): void
    {
        $selection = DesignSelection::find($this->designSelectionId);

        if (! $selection) {
            return;
        }

        $run = $selection->analysisRun;
        $dna = $run->currentWebsiteDna();
        $diagnosis = $run->currentDiagnosisReport();

        if (! $dna || ! $diagnosis) {
            $selection->update(['status' => 'failed', 'error_message' => 'Website DNA and diagnosis must be ready before generating a redesign.']);

            return;
        }

        try {
            $spec = $generateDesignSpecAction->execute(
                $run,
                $dna,
                $diagnosis,
                $selection->direction_key,
                $selection->preferences_json ?? [],
                $selection->refineFromDesignSpec,
                $selection->visual_qa_findings_json,
            );

            // Blueprint path: map content into the approved layout and validate the v1.5 block.
            // A failure here never loses the redesign — the legacy spec renders as before.
            $blueprintId = $selection->blueprint_id ?? $this->inheritedBlueprintId($selection);
            if ($blueprintId) {
                $result = $designSpecV15->attach($spec, $selection, $blueprintId);
                if (! $result['attached']) {
                    Log::warning('DesignSpec v1.5 blueprint block was not attached', ['selection' => $selection->id, 'errors' => $result['errors']]);
                } elseif (! $selection->refineFromDesignSpec) {
                    // First round: apply the recommended "sharper hero wording" now, instead of waiting for the owner to ask in the editor.
                    ($autoHero ?? app(AutoHeroService::class))->apply($run, $run->user_id ? User::find($run->user_id) : null);
                    $spec = $run->currentDesignSpec() ?? $spec;
                }
            }

            $selection->update(['status' => 'complete', 'design_spec_id' => $spec->id]);
        } catch (MetaApiException $e) {
            $selection->update(['status' => 'failed', 'error_message' => 'Redesign generation is temporarily unavailable. Please try again shortly.']);
        } catch (Throwable $e) {
            Log::error('Unhandled failure generating design spec', ['selection' => $selection->id, 'error' => $e->getMessage()]);
            $selection->update(['status' => 'failed', 'error_message' => 'Something went wrong while generating this REDO. Please try again.']);

            throw $e;
        }
    }
}
