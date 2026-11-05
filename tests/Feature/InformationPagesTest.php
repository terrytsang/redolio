<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InformationPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_footer_link_works(): void
    {
        foreach (['about', 'hackathon', 'changelog', 'roadmap', 'docs', 'security', 'evidence', 'privacy', 'terms', 'contact', 'status'] as $name) {
            $this->get(route("pages.$name"))->assertOk()->assertSee('REDOLIO');
        }
    }

    public function test_homepage_footer_links_to_them(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        foreach (['changelog', 'roadmap', 'docs', 'security', 'status', 'evidence-policy', 'about', 'built-at-meta-hackathon', 'privacy', 'terms', 'contact'] as $path) {
            $this->assertStringContainsString('/'.$path.'"', $html);
        }
        $this->assertStringContainsString('#features', $html);
        $this->assertStringContainsString('#how-it-works', $html);
    }

    public function test_status_json_has_checks_and_no_secrets(): void
    {
        $res = $this->getJson('/status.json')->assertOk();
        $res->assertJsonStructure(['status', 'label', 'checked_at', 'checks' => [['name', 'status', 'note']]]);
        $this->assertStringNotContainsString('sk_', $res->getContent());
    }
}
