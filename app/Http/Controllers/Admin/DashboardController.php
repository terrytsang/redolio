<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisRun;
use App\Models\SiteWatch;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Paying subscribers only (the Free plan costs 0 and isn't a subscription for revenue purposes).
        $paid = Subscription::query()
            ->where('subscriptions.status', 'active')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('plans.price_monthly_cents', '>', 0);

        return view('admin.dashboard', [
            'metaKeySet' => (bool) \App\Models\PlatformSetting::get('meta_api_key', config('meta_api.api_key')),
            'totalUsers' => User::where('role', 'user')->count(),
            'totalAnalyses' => AnalysisRun::where('is_watch_scan', false)->where('purpose', '!=', 'direction_capture')->count(),
            'totalWatched' => SiteWatch::where('is_enabled', true)->count(),
            'totalSubscriptions' => (clone $paid)->count(),
            'mrr' => (clone $paid)->sum('plans.price_monthly_cents') / 100,
            'latestUsers' => User::where('role', 'user')->with(['subscriptions' => fn ($q) => $q->where('status', 'active')->with('plan')])->latest()->limit(10)->get(),
            'latestWebsites' => AnalysisRun::where('is_watch_scan', false)->where('purpose', '!=', 'direction_capture')->with('user')->latest()->limit(10)->get(),
        ]);
    }
}
