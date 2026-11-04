<?php

namespace App\Services\Blueprint;

use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Models\DesignSpecAsset;
use App\Models\PreviewVersion;
use Illuminate\Support\Facades\DB;

/** Creates the next DesignSpec version from an existing one. History is never rewritten: every change is a new row. */
class DesignSpecVersioner
{
    public function create(DesignSpec $from, array $json): DesignSpec
    {
        // One transaction under a row lock on the run: two writers cannot take the same version number, and a failure leaves no half-made version.
        return DB::transaction(function () use ($from, $json) {
            AnalysisRun::whereKey($from->analysis_run_id)->lockForUpdate()->first();

            return $this->createLocked($from, $json);
        });
    }

    private function createLocked(DesignSpec $from, array $json): DesignSpec
    {
        $run = $from->analysisRun;
        $next = (int) $run->designSpecs()->max('version') + 1;

        $new = DesignSpec::create([
            'analysis_run_id' => $run->id, 'version' => $next, 'template_key' => $from->template_key, 'design_spec_json' => $json,
            'status' => 'valid', 'model_identifier' => $from->model_identifier, 'prompt_version' => $from->prompt_version,
        ]);

        foreach ($from->specAssets as $a) {
            DesignSpecAsset::create(['design_spec_id' => $new->id, 'asset_key' => $a->asset_key, 'component_role' => $a->component_role, 'usage_json' => $a->usage_json]);
        }
        $prev = PreviewVersion::where('design_spec_id', $from->id)->first();
        PreviewVersion::create([
            'analysis_run_id' => $run->id, 'design_spec_id' => $new->id, 'version_number' => $next,
            'render_status' => $prev->render_status ?? 'ok', 'quality_report_json' => $prev->quality_report_json ?? [], 'created_at' => now(),
        ]);

        return $new;
    }
}
