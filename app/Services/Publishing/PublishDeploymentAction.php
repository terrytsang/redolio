<?php

namespace App\Services\Publishing;

use App\Models\AnalysisRun;
use App\Models\Deployment;
use App\Models\DesignSpec;
use Illuminate\Support\Facades\File;

/**
 * Generates, validates, and activates a deployment. A failed validation
 * never touches the existing live files (AT-43) — the new artifact is only
 * moved into the served directory after it passes.
 */
class PublishDeploymentAction
{
    public function __construct(
        private readonly ProductionArtifactGenerator $generator,
        private readonly ArtifactValidator $validator,
    ) {}

    public function execute(AnalysisRun $run, DesignSpec $spec, string $slug): Deployment
    {
        $deploymentNumber = (int) $run->deployments()->max('deployment_number') + 1;
        $tempDir = storage_path("app/private/deployments-tmp/{$run->public_id}-{$deploymentNumber}");

        $this->generator->generate($run, $spec, $tempDir, includeProvenance: false);
        $validation = $this->validator->validate($tempDir, $spec);

        $deployment = Deployment::create([
            'analysis_run_id' => $run->id,
            'design_spec_id' => $spec->id,
            'slug' => $slug,
            'deployment_number' => $deploymentNumber,
            'status' => $validation['valid'] ? 'live' : 'failed',
            'validation_json' => $validation,
        ]);

        if (! $validation['valid']) {
            File::deleteDirectory($tempDir);

            return $deployment;
        }

        $finalDir = storage_path("app/private/sites/{$slug}");
        File::ensureDirectoryExists(dirname($finalDir));
        if (File::isDirectory($finalDir)) {
            File::deleteDirectory($finalDir);
        }
        File::moveDirectory($tempDir, $finalDir);

        $deployment->update([
            'artifact_path' => "sites/{$slug}",
            'artifact_hash' => hash('sha256', File::get("{$finalDir}/index.html")),
            'published_at' => now(),
        ]);

        $run->update([
            'active_deployment_id' => $deployment->id,
            'published_slug' => $run->published_slug ?? $slug,
        ]);

        return $deployment;
    }

    public function rollback(AnalysisRun $run, Deployment $target): bool
    {
        if ($target->analysis_run_id !== $run->id || $target->artifact_path === null || ! $run->published_slug) {
            return false;
        }

        // Every (re)publish writes into the same slug directory, so a prior
        // deployment's own artifact directory has likely been overwritten
        // since — re-render that deployment's DesignSpec fresh rather than
        // assuming old files are still on disk.
        $finalDir = storage_path("app/private/sites/{$run->published_slug}");
        $this->generator->generate($run, $target->designSpec, $finalDir, includeProvenance: false);

        $rollbackDeployment = Deployment::create([
            'analysis_run_id' => $run->id,
            'design_spec_id' => $target->design_spec_id,
            'slug' => $run->published_slug,
            'deployment_number' => (int) $run->deployments()->max('deployment_number') + 1,
            'status' => 'live',
            'artifact_path' => "sites/{$run->published_slug}",
            'artifact_hash' => hash('sha256', File::get("{$finalDir}/index.html")),
            'published_at' => now(),
            'validation_json' => ['valid' => true, 'errors' => [], 'note' => "Rollback to deployment #{$target->deployment_number}"],
        ]);

        $run->update(['active_deployment_id' => $rollbackDeployment->id]);

        return true;
    }
}
