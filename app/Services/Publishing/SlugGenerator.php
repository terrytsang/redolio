<?php

namespace App\Services\Publishing;

use App\Models\AnalysisRun;
use Illuminate\Support\Str;

class SlugGenerator
{
    private const RESERVED = [
        'www', 'app', 'api', 'admin', 'setup', 'dashboard', 'login', 'register',
        'pricing', 'storage', 'build', 'sites', 'exports', 'billing', 'account',
        'mail', 'ftp', 'cdn', 'static', 'assets', 'redo', 'redoweb', 'redolio',
    ];

    /**
     * @return array{valid: bool, error: ?string}
     */
    public function validate(string $slug): array
    {
        $normalized = Str::slug($slug);

        if ($normalized === '' || $normalized !== $slug) {
            return ['valid' => false, 'error' => 'Use lowercase letters, numbers and hyphens only.'];
        }

        if (strlen($slug) < 3) {
            return ['valid' => false, 'error' => 'Must be at least 3 characters.'];
        }

        if (in_array($slug, self::RESERVED, true)) {
            return ['valid' => false, 'error' => 'That address is reserved.'];
        }

        if (AnalysisRun::where('published_slug', $slug)->exists()) {
            return ['valid' => false, 'error' => 'That address is already taken.'];
        }

        return ['valid' => true, 'error' => null];
    }

    public function suggestFrom(string $businessName): string
    {
        $base = Str::slug($businessName) ?: 'my-site';
        $base = substr($base, 0, 40);

        $candidate = $base;
        $suffix = 1;
        while (AnalysisRun::where('published_slug', $candidate)->exists() || in_array($candidate, self::RESERVED, true)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }
}
