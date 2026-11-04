<?php

namespace App\Console\Commands;

use App\Jobs\RunVisualQaJob;
use App\Models\VisualQaReport;
use Illuminate\Console\Command;

/** Re-runs Visual QA for designs whose screenshots were captured by a given browser provider (e.g. after a rendering fix). */
class RecaptureVisualQa extends Command
{
    protected $signature = 'visualqa:recapture {--provider=browserbase : Only reports captured by this provider} {--dry-run : List what would be re-captured}';

    protected $description = 'Re-capture the Reveal screenshots made by a given browser provider';

    public function handle(): int
    {
        $provider = (string) $this->option('provider');
        $specIds = VisualQaReport::where('status', 'complete')->latest('id')->get()
            ->filter(fn ($r) => ($r->report_json['provider'] ?? null) === $provider)
            ->pluck('design_spec_id')->unique()->values();

        $this->info($specIds->count()." design(s) captured by {$provider}.");
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        foreach ($specIds as $id) {
            $report = VisualQaReport::create(['design_spec_id' => $id, 'status' => 'pending', 'created_at' => now()]);
            RunVisualQaJob::dispatch($report->id);
        }
        $this->info('Queued. The Reveal pages keep showing the previous screenshots until each new capture completes.');

        return self::SUCCESS;
    }
}
