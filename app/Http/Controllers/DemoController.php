<?php

namespace App\Http\Controllers;

use App\Models\DemoFixture;
use App\Services\Fixtures\FixtureMaterializer;
use Illuminate\Http\RedirectResponse;

class DemoController extends Controller
{
    public function show(DemoFixture $demoFixture, FixtureMaterializer $materializer): RedirectResponse
    {
        $run = $materializer->materialize($demoFixture);

        return redirect()->route('analyses.show', $run->public_id);
    }
}
