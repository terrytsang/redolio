<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_url_is_rejected_with_inline_error(): void
    {
        $response = $this->from('/')->post('/analyses', ['url' => 'not a url']);

        $response->assertSessionHasErrors('url');
    }

    public function test_private_localhost_url_is_rejected_before_fetching(): void
    {
        $response = $this->from('/')->post('/analyses', ['url' => 'http://127.0.0.1/']);

        $response->assertSessionHasErrors('url');
        $this->assertDatabaseCount('analysis_runs', 0);
    }

    public function test_cloud_metadata_url_is_rejected(): void
    {
        $response = $this->from('/')->post('/analyses', ['url' => 'http://169.254.169.254/latest/meta-data/']);

        $response->assertSessionHasErrors('url');
    }
}
