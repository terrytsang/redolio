<?php

namespace App\Services\Fixtures;

use App\Models\DemoFixture;
use Illuminate\Support\Collection;

/**
 * Loads prepared demo fixtures (FR-011). Fixtures use the exact same
 * schemas and renderer as the live path and are always clearly labelled
 * as fixture mode wherever they are displayed.
 */
class FixtureLoader
{
    public function find(string $fixtureKey): ?DemoFixture
    {
        return DemoFixture::where('fixture_key', $fixtureKey)->where('is_active', true)->first();
    }

    public function listActive(): Collection
    {
        return DemoFixture::where('is_active', true)->get(['fixture_key', 'display_name', 'description']);
    }
}
