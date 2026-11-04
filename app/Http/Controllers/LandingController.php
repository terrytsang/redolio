<?php

namespace App\Http\Controllers;

use App\Services\Fixtures\FixtureLoader;

class LandingController extends Controller
{
    public function index(FixtureLoader $fixtures)
    {
        return view('landing', [
            'demoFixtures' => $fixtures->listActive(),
        ]);
    }
}
