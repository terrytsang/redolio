<?php

namespace App\Http\Controllers;

use App\Exceptions\SsrfViolationException;
use App\Jobs\RunSiteWatchScanJob;
use App\Models\SiteWatch;
use App\Services\Crawler\UrlSubmissionValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteWatchController extends Controller
{
    public function store(Request $request, UrlSubmissionValidator $urlValidator): RedirectResponse
    {
        $data = $request->validate(['url' => ['required', 'string', 'max:2048'], 'frequency' => ['nullable', 'in:weekly,monthly']]);
        $days = ($data['frequency'] ?? 'monthly') === 'weekly' ? 7 : 30;

        $user = $request->user();
        $plan = $user->currentPlan();

        if (! $plan || ! $plan->auto_rescan_frequency_days) {
            return back()->with('status', 'Self-evolving auto-rescans require a paid plan. Upgrade to turn this on.');
        }

        $activeWatchCount = $user->siteWatches()->count();
        if ($plan->site_limit !== null && $activeWatchCount >= $plan->site_limit) {
            return back()->with('status', "Your {$plan->name} plan allows up to {$plan->site_limit} watched websites.");
        }

        try {
            $normalized = $urlValidator->validateAndNormalize($request->input('url'));
        } catch (SsrfViolationException $e) {
            throw ValidationException::withMessages([
                'url' => 'Please enter a valid, publicly reachable website address.',
            ]);
        }

        if ($user->siteWatches()->where('normalized_url', $normalized)->exists()) {
            return back()->with('status', 'This website is already being watched.');
        }

        SiteWatch::create([
            'user_id' => $user->id,
            'submitted_url' => $request->input('url'),
            'normalized_url' => $normalized,
            'frequency_days' => $days,
            'is_enabled' => true,
            'next_scan_at' => now(), // the first scan runs on the next hourly tick and becomes the baseline report
        ]);

        return back()->with('status', 'Watching this website. Your first report appears here after the first scan (within the hour).');
    }

    public function update(Request $request, SiteWatch $siteWatch): RedirectResponse
    {
        abort_unless($siteWatch->user_id === $request->user()->id, 403);
        $data = $request->validate(['frequency' => ['required', 'in:weekly,monthly']]);
        $days = $data['frequency'] === 'weekly' ? 7 : 30;
        $siteWatch->update(['frequency_days' => $days, 'next_scan_at' => ($siteWatch->last_scanned_at ?? now())->copy()->addDays($days)]);

        return back()->with('status', 'This website will now be rescanned '.strtolower($siteWatch->fresh()->frequencyLabel()).'.');
    }

    /** Run a scan now instead of waiting for the schedule (a few per day). */
    public function scanNow(Request $request, SiteWatch $siteWatch): RedirectResponse
    {
        abort_unless($siteWatch->user_id === $request->user()->id, 403);
        if (! $request->user()->currentPlan()?->auto_rescan_frequency_days) {
            return back()->with('status', 'Watch reports are part of the Starter and Agency plans.');
        }
        if ($siteWatch->last_status === 'running') {
            return back()->with('status', 'A scan of this website is already running.');
        }
        if ($siteWatch->last_scanned_at && $siteWatch->last_scanned_at->gt(now()->subHours(6))) {
            return back()->with('status', 'This website was scanned less than 6 hours ago — check the report below.');
        }
        $siteWatch->update(['last_status' => 'running', 'last_error' => null, 'next_scan_at' => now()->addDays($siteWatch->frequency_days)]);
        RunSiteWatchScanJob::dispatch($siteWatch->id);

        return back()->with('status', 'Scanning now — the report appears here in a few minutes.');
    }

    public function toggle(Request $request, SiteWatch $siteWatch): RedirectResponse
    {
        abort_unless($siteWatch->user_id === $request->user()->id, 403);

        $siteWatch->update(['is_enabled' => ! $siteWatch->is_enabled]);

        return back()->with('status', $siteWatch->is_enabled ? 'Auto-evolve resumed.' : 'Auto-evolve paused.');
    }

    public function destroy(Request $request, SiteWatch $siteWatch): RedirectResponse
    {
        abort_unless($siteWatch->user_id === $request->user()->id, 403);

        $siteWatch->delete();

        return back()->with('status', 'Website removed from auto-evolve.');
    }
}
