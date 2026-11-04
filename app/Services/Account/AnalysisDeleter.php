<?php

namespace App\Services\Account;

use App\Models\AnalysisRun;
use App\Models\Export;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/** Permanently removes one website analysis (and its designs, pictures, screenshots, published copy and downloads), freeing a place on the plan. */
class AnalysisDeleter
{
    public function delete(AnalysisRun $run): void
    {
        $pid = $run->public_id;
        $slug = $run->published_slug;
        $exports = Export::where('analysis_run_id', $run->id)->pluck('storage_path')->all();

        $run->delete(); // every table that hangs off a run is set to cascade

        Storage::disk('public')->deleteDirectory("site-assets/{$pid}");
        Storage::disk('public')->deleteDirectory("visual-qa/{$pid}");
        if ($slug && preg_match('/^[a-z0-9-]+$/', $slug)) {
            File::deleteDirectory(storage_path("app/private/sites/{$slug}"));
        }
        foreach ($exports as $path) {
            $path && Storage::disk('local')->delete($path);
        }
    }
}
