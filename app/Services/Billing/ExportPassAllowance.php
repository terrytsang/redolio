<?php

namespace App\Services\Billing;

use App\Models\AnalysisRun;
use App\Models\OneTimePurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * What an Export Pass includes for ONE website, for people on the Free plan: the first download plus two more, and two more saved
 * edits. Starter and Agency are unlimited, and a Free-plan person who has not bought a pass for a website keeps editing it freely
 * (they are still deciding). A pass bought from a website's upgrade page belongs to that website; one bought elsewhere attaches to
 * the first website it is used to download.
 */
class ExportPassAllowance
{
    public function unlimited(User $user): bool
    {
        if (config('redo.edition') === 'selfhosted') {
            return true;
        }
        $plan = $user->currentPlan();

        return (bool) ($plan?->static_export_enabled || $plan?->publishing_enabled);
    }

    /** @return Builder<OneTimePurchase> */
    private function passes(User $user): Builder
    {
        return OneTimePurchase::where('user_id', $user->id)->where('purchase_type', 'export_pass')->where('status', 'paid');
    }

    private function bound(User $user, AnalysisRun $run): Builder
    {
        return $this->passes($user)->where('analysis_run_id', $run->id);
    }

    /**
     * What is left on this website's pass, or null when no limit applies (paid plan, or no pass for this website yet).
     *
     * @return array{downloads: int, edits: int}|null
     */
    public function capFor(User $user, AnalysisRun $run): ?array
    {
        if ($this->unlimited($user)) {
            return null;
        }
        $bound = $this->bound($user, $run)->get();
        if ($bound->isEmpty()) {
            return null;
        }

        return ['downloads' => (int) $bound->sum('exports_remaining'), 'edits' => (int) $bound->sum('saves_remaining')];
    }

    public function canExport(User $user, AnalysisRun $run): bool
    {
        if ($this->unlimited($user)) {
            return true;
        }
        if ($this->bound($user, $run)->where('exports_remaining', '>', 0)->exists()) {
            return true;
        }

        return $this->passes($user)->whereNull('analysis_run_id')->where('exports_remaining', '>', 0)->exists();
    }

    /** Use one download. A pass not yet attached to a website attaches to this one. */
    public function consumeExport(User $user, AnalysisRun $run): bool
    {
        if ($this->unlimited($user)) {
            return true;
        }
        $pass = $this->bound($user, $run)->where('exports_remaining', '>', 0)->oldest()->first()
            ?? $this->passes($user)->whereNull('analysis_run_id')->where('exports_remaining', '>', 0)->oldest()->first();
        if (! $pass) {
            return false;
        }
        $pass->analysis_run_id ??= $run->id;
        $pass->exports_remaining--;
        $pass->save();

        return true;
    }

    public function consumeEdit(User $user, AnalysisRun $run): void
    {
        if ($this->unlimited($user)) {
            return;
        }
        $this->bound($user, $run)->where('saves_remaining', '>', 0)->oldest()->first()?->decrement('saves_remaining');
    }
}
