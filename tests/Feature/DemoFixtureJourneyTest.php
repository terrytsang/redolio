<?php

namespace Tests\Feature;

use Database\Seeders\DemoFixturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoFixtureJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_fixture_completes_the_full_journey_without_live_api(): void
    {
        $this->seed(DemoFixturesSeeder::class);

        $response = $this->get('/demo/brightleaf-dental');
        $response->assertRedirect();

        $statusResponse = $this->get($response->headers->get('Location'));
        $statusResponse->assertStatus(200);
        $statusResponse->assertSee('Fixture demo mode', false);

        $publicId = basename($response->headers->get('Location'));

        $this->get("/analyses/{$publicId}/website-dna")->assertStatus(200);
        $this->get("/analyses/{$publicId}/diagnosis")->assertStatus(200)->assertSee('Learn More', false);
        $this->get("/analyses/{$publicId}/preview")->assertStatus(200)->assertSee('Book an Appointment', false);
    }

    public function test_second_fixture_also_completes_the_journey(): void
    {
        $this->seed(DemoFixturesSeeder::class);

        $response = $this->get('/demo/anchor-consulting');
        $response->assertRedirect();

        $this->get($response->headers->get('Location'))->assertStatus(200);
    }
}
