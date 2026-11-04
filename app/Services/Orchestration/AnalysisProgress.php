<?php

namespace App\Services\Orchestration;

use App\Models\AnalysisRun;
use App\Models\AnalysisStageLog;
use Illuminate\Support\Facades\Cache;

/**
 * Honest progress for the scan page: a percentage and an estimated time left, plus queue position when the system
 * is busy. Estimates come from the real durations of recently completed stages (falling back to defaults), and
 * are labelled as estimates — the percentage never reaches 100 until the scan is actually finished.
 */
class AnalysisProgress
{
    /** Default seconds per stage until real history exists. */
    private const DEFAULTS = ['validating_url' => 3, 'crawling' => 45, 'building_dna' => 35, 'diagnosing' => 30, 'preparing_redesign' => 50];

    public const STAGES = ['validating_url' => 'Validate URL', 'crawling' => 'Crawl pages', 'building_dna' => 'Website DNA', 'diagnosing' => 'Diagnose', 'preparing_redesign' => 'REDO'];

    /** @return array<string, float> seconds per stage, from the last completed stages */
    public function stageDurations(): array
    {
        return Cache::remember('analysis_stage_durations', 300, function () {
            $out = self::DEFAULTS;
            foreach (array_keys(self::DEFAULTS) as $stage) {
                $d = AnalysisStageLog::where('stage', $stage)->whereIn('status', ['complete', 'partial'])->whereNotNull('started_at')->whereNotNull('completed_at')
                    ->orderByDesc('id')->limit(30)->get()
                    ->map(fn ($l) => $l->started_at->diffInSeconds($l->completed_at, true))->filter(fn ($s) => $s > 0)->sort()->values();
                if ($d->count() >= 3) { // median, so one slow site does not skew everyone's estimate
                    $out[$stage] = max(2.0, (float) $d[intdiv($d->count(), 2)]);
                }
            }

            return $out;
        });
    }

    public function totalSeconds(): float
    {
        return array_sum($this->stageDurations());
    }

    /** Scans in flight (excluding admin direction-capture runs, which never appear to users). */
    public function inFlight(): array
    {
        $q = AnalysisRun::whereIn('status', ['pending', 'running'])->where(fn ($q) => $q->whereNull('purpose')->orWhere('purpose', '!=', 'direction_capture'));

        return ['running' => (clone $q)->where('status', 'running')->count(), 'waiting' => (clone $q)->where('status', 'pending')->count()];
    }

    public function snapshot(AnalysisRun $run): array
    {
        $durations = $this->stageDurations();
        $total = array_sum($durations);
        $done = in_array($run->status, ['complete', 'partial'], true);
        $failed = $run->status === 'failed';
        $logs = $run->stageLogs()->orderBy('id')->get();

        $stages = [];
        $doneSeconds = 0.0;
        $remaining = 0.0;
        foreach (self::STAGES as $key => $label) {
            $log = $logs->where('stage', $key)->last();
            $state = $done ? 'complete' : ($log?->status ?? ($run->current_stage === $key && ! $failed ? 'running' : 'pending'));
            $est = $durations[$key];
            if (in_array($state, ['complete', 'partial'], true)) {
                $doneSeconds += $est;
            } elseif ($state === 'running') {
                $elapsed = $log?->started_at ? $log->started_at->diffInSeconds(now(), true) : 0;
                $doneSeconds += min($elapsed, $est * 0.9);          // never claim a stage is finished before it is
                $remaining += max($est - $elapsed, min($est * 0.15, 8)); // and always leave a little time on the clock
            } else {
                $remaining += $est;
            }
            $stages[] = ['key' => $key, 'label' => $label, 'state' => $state];
        }

        $queue = $this->queue($run, $total);
        $percent = $done ? 100 : ($failed ? (int) round(100 * $doneSeconds / $total) : min(99, (int) floor(100 * $doneSeconds / $total)));
        $eta = $done || $failed ? 0 : (int) round($remaining + ($queue['wait_seconds'] ?? 0));

        return [
            'status' => $run->status, 'stage' => $run->current_stage, 'percent' => $percent, 'eta_seconds' => $eta, 'eta_text' => $done || $failed ? null : self::etaText($eta),
            'stages' => $stages, 'queue' => $queue, 'signature' => $run->status.'|'.$run->current_stage.'|'.collect($stages)->pluck('state')->implode(','),
            'notify_email' => $run->notify_email ? true : false,
        ];
    }

    /** @return array{busy: bool, waiting: bool, position: ?int, scans_ahead: int, wait_seconds: int} */
    private function queue(AnalysisRun $run, float $total): array
    {
        $cap = config('redo.capacity');
        $f = $this->inFlight();
        $busy = ($f['running'] + $f['waiting']) >= $cap['busy_threshold'];
        $waiting = $run->status === 'pending';
        $position = null;
        $wait = 0;
        if ($waiting) {
            $ahead = AnalysisRun::where('status', 'pending')->where('id', '<', $run->id)->where(fn ($q) => $q->whereNull('purpose')->orWhere('purpose', '!=', 'direction_capture'))->count();
            $position = $ahead + 1;
            $slotsBusy = $f['running'];
            // Everything running or ahead of us must clear a worker first; each wave takes roughly half a scan on average (they are part-way through).
            $beforeUs = $slotsBusy + $ahead;
            $wait = $beforeUs < $cap['workers'] ? 0 : (int) round((intdiv($beforeUs - $cap['workers'], max(1, $cap['workers'])) + 1) * $total * 0.6);
        }

        return ['busy' => $busy, 'waiting' => $waiting, 'position' => $position, 'scans_ahead' => $position ? $position - 1 + $f['running'] : $f['running'] + $f['waiting'], 'wait_seconds' => $wait];
    }

    public static function etaText(int $seconds): string
    {
        return match (true) {
            $seconds < 20 => 'Less than 30 seconds left',
            $seconds < 75 => 'About 1 minute left',
            default => 'About '.(int) ceil($seconds / 60).' minutes left',
        };
    }
}
