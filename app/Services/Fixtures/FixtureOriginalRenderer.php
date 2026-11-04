<?php

namespace App\Services\Fixtures;

/**
 * Renders an ILLUSTRATIVE "original website" for a demo fixture from the fixture's own
 * recorded evidence (title, headings, text, links, calls to action, offerings) in a
 * deliberately dated style, so demos have a real before to compare against. It is labelled
 * as illustrative wherever it is shown; real analyses always use a capture of the real site.
 */
class FixtureOriginalRenderer
{
    public function render(array $payload): string
    {
        $page = $payload['snapshot']['pages'][0] ?? [];
        $dna = $payload['website_dna'] ?? [];
        $name = $this->e($dna['business_name'] ?? $page['title'] ?? 'Welcome');
        $heading = $this->e($page['headings'][0] ?? "Welcome to {$name}");
        $text = $this->e($page['visible_text'] ?? '');
        $cta = $this->e($page['ctas'][0] ?? 'Click Here');
        $offerings = array_map(fn ($o) => $this->e($o['name']), $dna['offerings'] ?? []);
        $links = ['Home', 'About Us', 'Services', 'Contact Us'];
        $li = fn (array $items) => implode('', array_map(fn ($i) => "<li>{$i}</li>", $items));
        $nav = implode('', array_map(fn ($l) => "<div class=\"nav\"><a href=\"#\">{$l}</a></div>", $links));

        return <<<HTML
<!doctype html><html><head><meta charset="utf-8"><title>{$name}</title>
<style>
body{margin:0;background:#c9d6e8 url() repeat;font-family:Verdana,Geneva,sans-serif;font-size:12px;color:#000}
#wrap{width:900px;margin:12px auto;background:#fff;border:2px solid #003366}
#banner{background:linear-gradient(#336699,#003366);color:#ffcc00;text-align:center;padding:26px 10px;border-bottom:4px double #ffcc00}
#banner h1{font:bold 34px "Times New Roman",serif;margin:0;text-shadow:2px 2px #000}
#banner p{margin:6px 0 0;color:#fff;font-style:italic}
#cols{display:table;width:100%}
#side{display:table-cell;width:170px;background:#e6edf7;border-right:1px solid #99a;padding:10px;vertical-align:top}
.nav{border:2px outset #ccc;background:#dde;margin-bottom:6px;padding:4px 8px;font-weight:bold}
.nav a{color:#0000cc}
#main{display:table-cell;padding:16px 22px;vertical-align:top}
h2{font:bold 22px "Comic Sans MS","Comic Sans",cursive;color:#cc0000;margin:0 0 10px}
p{line-height:1.45}
ul{margin:8px 0 14px 18px}
li{margin-bottom:4px}
.btn{display:inline-block;border:3px outset #ddd;background:#ccc;padding:5px 16px;font-weight:bold;color:#000;text-decoration:none;margin:8px 0}
.hit{background:#ffff99;border:1px dashed #999;padding:6px;margin:12px 0;text-align:center;font-weight:bold}
#foot{background:#003366;color:#ccc;text-align:center;padding:10px;font-size:10px}
</style></head><body><div id="wrap">
<div id="banner"><h1>{$name}</h1><p>~ Your trusted local choice ~</p></div>
<div id="cols"><div id="side">{$nav}<div class="hit">Call today!</div></div>
<div id="main"><h2>{$heading}</h2><p>{$text}</p><div class="hit">*** NEW *** Now accepting new customers ***</div>
<p><b>What we offer:</b></p><ul>{$li($offerings)}</ul><a class="btn" href="#">{$cta}</a>
<p style="font-size:10px;color:#666">Click the links on the left to find out more about us.</p></div></div>
<div id="foot">&copy; {$name}. All rights reserved. | Sitemap | Privacy | Links</div></div></body></html>
HTML;
    }

    private function e(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
