<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Country and city for a visitor's IP address. The address itself is never stored — only the place. Results are cached
 * per address for a week so each visitor costs at most one lookup, and a slow or failed lookup never holds up a page.
 */
class GeoLocator
{
    /** @return array{country: ?string, city: ?string} */
    public function locate(?string $ip): array
    {
        $none = ['country' => null, 'city' => null];
        if (! config('redo.geoip_enabled') || ! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $none;
        }

        return Cache::remember('geo:'.hash('sha256', $ip), now()->addDays(7), function () use ($ip, $none) {
            try {
                $r = Http::timeout(2)->get('https://ipwho.is/'.rawurlencode($ip), ['fields' => 'success,country,city']);

                return $r->successful() && $r->json('success') ? ['country' => $r->json('country') ?: null, 'city' => $r->json('city') ?: null] : $none;
            } catch (Throwable) {
                return $none;
            }
        });
    }
}
