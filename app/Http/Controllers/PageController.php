<?php

namespace App\Http\Controllers;

use App\Services\Status\SystemStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

/** The information pages linked from the homepage footer. */
class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about');
    }

    public function hackathon(): View
    {
        return view('pages.hackathon');
    }

    public function changelog(): View
    {
        return view('pages.changelog');
    }

    public function roadmap(): View
    {
        return view('pages.roadmap');
    }

    public function docs(): View
    {
        return view('pages.docs');
    }

    public function security(): View
    {
        return view('pages.security');
    }

    public function evidence(): View
    {
        return view('pages.evidence');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function status(SystemStatus $status): View
    {
        return view('pages.status', ['report' => $status->report()]);
    }

    public function statusJson(SystemStatus $status): JsonResponse
    {
        return response()->json($status->report())->header('Cache-Control', 'no-store');
    }
}
