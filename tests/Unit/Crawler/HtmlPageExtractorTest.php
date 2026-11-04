<?php

namespace Tests\Unit\Crawler;

use App\Services\Crawler\HtmlPageExtractor;
use Tests\TestCase;

class HtmlPageExtractorTest extends TestCase
{
    public function test_extracts_normalized_content_and_strips_scripts(): void
    {
        $html = <<<'HTML'
            <html>
            <head>
                <title>Example Co</title>
                <meta name="description" content="We make things.">
                <link rel="canonical" href="https://example.com/">
            </head>
            <body>
                <script>alert('should not appear');</script>
                <style>.x { color: red; }</style>
                <h1>Welcome</h1>
                <p>We build great products for small businesses.</p>
                <a href="/contact">Contact us</a>
                <button>Get Started</button>
                <img src="/a.png" alt="Team photo">
                <a href="mailto:hello@example.com">Email us</a>
                <form action="/submit">
                    <label>Name</label>
                    <input type="text" name="name">
                    <input type="submit" value="Send">
                </form>
            </body>
            </html>
            HTML;

        $extractor = new HtmlPageExtractor;
        $page = $extractor->extract('https://example.com/', 200, $html);

        $this->assertSame('Example Co', $page->title);
        $this->assertSame('We make things.', $page->metaDescription);
        $this->assertSame('https://example.com/', $page->canonicalUrl);
        $this->assertSame(['Welcome'], $page->headings);
        $this->assertStringNotContainsString('should not appear', $page->visibleText);
        $this->assertStringContainsString('We build great products', $page->visibleText);
        $this->assertContains('https://example.com/contact', array_column($page->links, 'href'));
        $this->assertContains('Contact us', $page->ctas);
        $this->assertContains('Get Started', $page->ctas);
        $this->assertContains('Team photo', $page->imageAlt);
        $this->assertContains('hello@example.com', $page->contactDetails);
        $this->assertCount(1, $page->forms);
        $this->assertSame('success', $page->fetchStatus);
    }

    public function test_extracts_image_candidates_favicon_and_theme_color(): void
    {
        $html = <<<'HTML'
            <html>
            <head>
                <title>Example Co</title>
                <link rel="icon" href="/favicon.ico">
                <meta name="theme-color" content="#112233">
            </head>
            <body>
                <nav><img src="/logo.png" alt="Acme Logo" width="120" height="40"></nav>
                <header><h1>Welcome</h1><img src="https://cdn.example.com/hero.jpg" alt="Hero"></header>
                <main><section><h2>Our Team</h2><img src="team1.jpg" alt="Jane Doe portrait" width="300" height="300"></section></main>
            </body>
            </html>
            HTML;

        $extractor = new HtmlPageExtractor;
        $page = $extractor->extract('https://example.com/', 200, $html);

        $this->assertSame('https://example.com/favicon.ico', $page->faviconUrl);
        $this->assertSame('#112233', $page->themeColor);
        $this->assertCount(3, $page->imageCandidates);

        $logo = $page->imageCandidates[0];
        $this->assertSame('https://example.com/logo.png', $logo['src']);
        $this->assertSame('nav', $logo['dom_context']);
        $this->assertSame(120, $logo['width']);

        $portrait = $page->imageCandidates[2];
        $this->assertSame('https://example.com/team1.jpg', $portrait['src']);
        $this->assertSame('section', $portrait['dom_context']);
        $this->assertSame('Our Team', $portrait['nearby_heading']);
    }

    public function test_ignores_data_uri_images(): void
    {
        $html = '<html><body><img src="data:image/png;base64,iVBORw0KGgo="></body></html>';

        $extractor = new HtmlPageExtractor;
        $page = $extractor->extract('https://example.com/', 200, $html);

        $this->assertEmpty($page->imageCandidates);
    }

    public function test_flags_pages_with_little_extractable_text_as_warning(): void
    {
        $extractor = new HtmlPageExtractor;
        $page = $extractor->extract('https://example.com/app', 200, '<html><body><div id="root"></div></body></html>');

        $this->assertNotEmpty($page->warnings);
    }

    public function test_handles_empty_body(): void
    {
        $extractor = new HtmlPageExtractor;
        $page = $extractor->extract('https://example.com/', 200, '');

        $this->assertSame('failed', $page->fetchStatus);
    }
}
