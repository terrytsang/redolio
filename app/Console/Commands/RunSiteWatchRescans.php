<?php

namespace App\Console\Commands;

use App\Jobs\RunSiteWatchScanJob;
use App\Models\SiteWatch;
use Illuminate\Console\Command;

class RunSiteWatchRescans extends Command
{
    protected $signature = 'redo:run-site-watches';

    protected $description = 'Queue a rescan + Watch report for every due, enabled site watch of a paid plan (the self-evolving mechanism).';

    public function handle(): int
    {
        $due = SiteWatch::query()->where('is_enabled', true)->where(fn ($q) => $q->whereNull('next_scan_at')->orWhere('next_scan_at', '<=', now()))->with('user')->get()
            ->filter(fn ($w) => $w->user?->is_active && $w->user->currentPlan()?->auto_rescan_frequency_days); // paid plans only

        $this->info("Found {$due->count()} due site watch(es).");
        foreach ($due as $watch) {
            // Move the clock first, so an hourly tick can never queue the same watch twice.
            $watch->update(['next_scan_at' => now()->addDays($watch->frequency_days)]);
            RunSiteWatchScanJob::dispatch($watch->id);
        }

        return self::SUCCESS;
    }
}
