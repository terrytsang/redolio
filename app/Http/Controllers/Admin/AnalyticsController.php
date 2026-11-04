<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Website traffic: every visit with its date, place, device and source. */
class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $days = in_array((int) $request->query('days'), [1, 7, 30, 90], true) ? (int) $request->query('days') : 30;
        $device = in_array($request->query('device'), ['mobile', 'tablet', 'desktop'], true) ? $request->query('device') : null;

        $base = PageView::query()
            ->where('created_at', '>=', now()->subDays($days)->startOfDay())
            ->when($device, fn ($q) => $q->where('device', $device))
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('country', 'like', $t)->orWhere('city', 'like', $t)->orWhere('source', 'like', $t)->orWhere('path', 'like', $t));
            });

        $top = fn (string $col) => (clone $base)->whereNotNull($col)->where($col, '!=', '')->selectRaw("$col as label, count(*) as visits")->groupBy($col)->orderByDesc('visits')->limit(5)->get();

        return view('admin.analytics', [
            'days' => $days,
            'device' => $device,
            'q' => $request->string('q')->toString(),
            'visits' => (clone $base)->count(),
            'visitors' => (clone $base)->whereNotNull('visitor_hash')->distinct()->count('visitor_hash'),
            'topCountries' => $top('country'),
            'topSources' => $top('source'),
            'devices' => (clone $base)->whereNotNull('device')->selectRaw('device as label, count(*) as visits')->groupBy('device')->orderByDesc('visits')->get(),
            'rows' => (clone $base)->latest('created_at')->latest('id')->paginate(50)->withQueryString(),
        ]);
    }
}
