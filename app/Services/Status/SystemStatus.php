<?php

namespace App\Services\Status;

use App\Services\Billing\Billing;
use App\Services\VisualQa\BrowserService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What the public "API status" page shows: whether each part REDOLIO depends on is working right now. Checked live (and kept for a
 * minute so the page can't be used to hammer the services). It never reveals keys, hostnames or other internals.
 */
class SystemStatus
{
    public const OK = 'operational';

    public const SLOW = 'degraded';

    public const DOWN = 'down';

    public const OFF = 'not_in_use';

    /** @return array{status: string, label: string, checked_at: string, checks: array<int, array{name: string, status: string, note: string}>} */
    public function report(): array
    {
        return Cache::remember('system-status', 60, function () {
            $checks = [
                $this->check('Website and sign-in', fn () => [self::OK, 'Pages and accounts are working.']),
                $this->check('Database', function () {
                    DB::select('select 1');

                    return [self::OK, 'Your analyses and designs are saved and loading.'];
                }),
                $this->check('Background jobs', fn () => $this->jobs()),
                $this->check('AI (Meta Model API)', fn () => $this->ai()),
                $this->check('Screenshots and visual checks', fn () => $this->browser()),
            ];
            if (config('redo.edition') !== 'selfhosted') {
                $checks[] = $this->check('Payments (Stripe)', fn () => app(Billing::class)->enabled() ? [self::OK, 'Checkout and billing are available.'] : [self::OFF, 'Payments are not switched on.']);
            }
            $checks[] = $this->check('Email', fn () => config('mail.default') === 'log' ? [self::OFF, 'Email is not switched on.'] : [self::OK, 'Emails are being sent.']);

            $statuses = array_column($checks, 'status');
            $overall = in_array(self::DOWN, $statuses, true) ? self::DOWN : (in_array(self::SLOW, $statuses, true) ? self::SLOW : self::OK);

            return [
                'status' => $overall,
                'label' => ['operational' => 'All systems operational', 'degraded' => 'Some things are slower than usual', 'down' => 'We’re having a problem'][$overall],
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
            ];
        });
    }

    private function check(string $name, \Closure $fn): array
    {
        try {
            [$status, $note] = $fn();
        } catch (Throwable) {
            [$status, $note] = [self::DOWN, 'This is not responding right now.'];
        }

        return ['name' => $name, 'status' => $status, 'note' => $note];
    }

    private function jobs(): array
    {
        $oldest = DB::table('jobs')->where('available_at', '<=', now()->timestamp)->min('created_at');
        $waitMinutes = $oldest ? (int) floor((now()->timestamp - (int) $oldest) / 60) : 0;

        return $waitMinutes >= 10
            ? [self::SLOW, 'Analyses and designs are queued behind a delay.']
            : [self::OK, 'Analyses and designs are being processed.'];
    }

    private function ai(): array
    {
        $key = config('meta_api.api_key');

        return $key ? [self::OK, 'The AI that reads and redesigns websites is connected.'] : [self::OFF, 'The AI provider is not connected yet.'];
    }

    private function browser(): array
    {
        return app(BrowserService::class)->isAvailable() ? [self::OK, 'Screenshots and design checks are working.'] : [self::DOWN, 'Screenshots are unavailable right now.'];
    }
}
