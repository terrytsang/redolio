<?php

namespace App\Services\Crawler;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Best-effort robots.txt compliance check. This is a product/ethics rule,
 * not a security boundary — SsrfGuard is the security boundary and runs
 * independently regardless of what robots.txt says.
 */
class RobotsPolicy
{
    public function __construct(private readonly SafeHttpFetcher $fetcher) {}

    public function isAllowed(string $origin, string $path, string $userAgent): bool
    {
        $rules = Cache::remember("robots:{$origin}", 300, function () use ($origin) {
            return $this->fetchRules($origin);
        });

        if ($rules === null) {
            return true;
        }

        foreach ($rules as $rule) {
            if ($rule['agent'] !== '*' && ! str_contains(strtolower($userAgent), strtolower($rule['agent']))) {
                continue;
            }

            if ($rule['directive'] === 'disallow' && $rule['path'] !== '' && str_starts_with($path, $rule['path'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array{agent: string, directive: string, path: string}>|null
     */
    private function fetchRules(string $origin): ?array
    {
        try {
            $result = $this->fetcher->fetch(rtrim($origin, '/').'/robots.txt');
        } catch (Throwable) {
            return null;
        }

        if ($result->httpStatus !== 200) {
            return null;
        }

        $rules = [];
        $currentAgent = '*';

        foreach (preg_split('/\r?\n/', $result->body) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line) ?? '');

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);

            if ($key === 'user-agent') {
                $currentAgent = $value;
            } elseif ($key === 'disallow') {
                $rules[] = ['agent' => $currentAgent, 'directive' => 'disallow', 'path' => $value];
            }
        }

        return $rules;
    }
}
