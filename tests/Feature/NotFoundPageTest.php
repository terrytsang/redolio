<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_page_shows_the_friendly_404_that_invites_the_visitor_to_redo_their_site(): void
    {
        $r = $this->get('/this-page-does-not-exist')->assertNotFound();
        $r->assertSee('Well, this is')->assertSee('awkward.')->assertSee('Let’s REDO it →', false)->assertSee('your')->assertSee('Page not found — REDOLIO', false)
            ->assertSee('action="'.route('landing').'"', false)->assertSee('support@example.test');
        $this->assertStringNotContainsString('Symfony', $r->getContent());
    }

    public function test_the_404_form_hands_the_address_to_the_homepage_box(): void
    {
        $this->get('/?url=https://my-shop.example')->assertOk()->assertSee('value="https://my-shop.example"', false);
    }
}
