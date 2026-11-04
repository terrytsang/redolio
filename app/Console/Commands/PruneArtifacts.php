<?php

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Data retention (PRD v1.5: "data retention is documented"). Removes QA screenshots past
 * their retention window, abandoned temp build directories, and expired export packages.
 * Published sites, deployments, specs and the catalogue previews are never touched.
 */
class PruneArtifacts extends Command
{
    protected $signature = 'redo:prune {--dry-run : List what would be removed without deleting}';

    protected $description = 'Delete expired QA screenshots, stale temp builds and expired exports';

    private const TEMP_DIRS = ['visual-qa-tmp', 'deployments-tmp', 'exports-tmp', 'direction-showcase', 'fixture-originals'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $cfg = config('redo.retention');
        $removed = ['screenshots' => 0, 'temp' => 0, 'exports' => 0];

        // 1. QA screenshots (public disk) older than the window.
        $disk = Storage::disk('public');
        $cutoff = now()->subDays($cfg['qa_screenshot_days'])->getTimestamp();
        foreach ($disk->allFiles('visual-qa') as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $dry || $disk->delete($file);
                $removed['screenshots']++;
            }
        }
        $dry || collect($disk->directories('visual-qa'))->each(fn ($d) => $disk->allFiles($d) === [] && $disk->deleteDirectory($d));

        // 2. Temp build directories left behind by crashed jobs.
        $tempCutoff = now()->subHours($cfg['temp_hours'])->getTimestamp();
        foreach (self::TEMP_DIRS as $name) {
            $base = storage_path("app/private/{$name}");
            foreach (File::isDirectory($base) ? File::directories($base) : [] as $dir) {
                if (File::lastModified($dir) < $tempCutoff) {
                    $dry || File::deleteDirectory($dir);
                    $removed['temp']++;
                }
            }
            foreach (File::isDirectory($base) ? File::files($base) : [] as $file) {
                if (File::lastModified($file->getPathname()) < $tempCutoff) {
                    $dry || File::delete($file->getPathname());
                    $removed['temp']++;
                }
            }
        }

        // 3. Export packages well past their download window.
        Export::where('expires_at', '<', now()->subDays($cfg['export_days_after_expiry']))->each(function (Export $export) use ($dry, &$removed) {
            $dry || Storage::disk('local')->delete($export->storage_path);
            $dry || $export->delete();
            $removed['exports']++;
        });

        $this->line(($dry ? '[dry run] would remove' : 'Removed').": {$removed['screenshots']} QA screenshot(s), {$removed['temp']} temp item(s), {$removed['exports']} expired export(s).");

        return self::SUCCESS;
    }
}
