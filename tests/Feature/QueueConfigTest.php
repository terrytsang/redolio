<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueueConfigTest extends TestCase
{
    public function test_a_running_job_is_never_handed_to_a_second_worker(): void
    {
        $longest = 900; // the workers' --timeout (see the crontab in docs) — also the cap for any job's own $timeout
        foreach (glob(app_path('Jobs/*.php')) as $file) {
            if (preg_match('/public int \$timeout = (\d+)/', file_get_contents($file), $m)) {
                $this->assertLessThanOrEqual($longest, (int) $m[1], basename($file).' must not outlast the worker timeout');
            }
        }
        $this->assertGreaterThan($longest, (int) config('queue.connections.database.retry_after'), 'retry_after must exceed the longest possible job, or a long AI job is re-taken mid-run and failed as "attempted too many times"');
    }
}
