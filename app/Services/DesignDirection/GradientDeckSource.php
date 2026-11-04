<?php

namespace App\Services\DesignDirection;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a style from GradientDeck UI (https://ui.gradientdeck.com): a plain-Markdown DESIGN.md written from a real website
 * (colors with roles, typography, spacing, components, layout, do's and don'ts). The site publishes them free, with no account, at
 * stable URLs, as a reference for building your own interface. Only that one host is ever contacted, over https.
 */
class GradientDeckSource
{
    private const HOST = 'ui.gradientdeck.com';

    private const MAX_BYTES = 200_000;

    /** Accepts https://ui.gradientdeck.com/styles/kobbe, …/kobbe/design.md, …/kobbe/design-extended.md, or just "kobbe". */
    public function slugFrom(string $input): string
    {
        $input = trim($input);
        if (preg_match('#^[a-z0-9][a-z0-9-]{0,80}$#i', $input)) {
            return strtolower($input);
        }
        $parts = parse_url($input);
        if (! $parts || strtolower((string) ($parts['host'] ?? '')) !== self::HOST || ($parts['scheme'] ?? '') !== 'https'
            || ! preg_match('#^/styles/([a-z0-9][a-z0-9-]{0,80})(?:/design(?:-extended)?\.md)?/?$#i', (string) ($parts['path'] ?? ''), $m)) {
            throw new RuntimeException('Please paste a GradientDeck style address such as https://ui.gradientdeck.com/styles/kobbe');
        }

        return strtolower($m[1]);
    }

    /**
     * @return array{slug: string, name: string, nickname: string, host: ?string, industry: ?string, theme: ?string, colors: array<int, array<string, string>>, markdown: string, style_url: string, design_md_url: string}
     */
    public function fetch(string $slug): array
    {
        $base = 'https://'.self::HOST.'/styles/'.rawurlencode($slug);
        $md = $this->get("$base/design-extended.md", false) ?? $this->get("$base/design.md", true);
        if (trim($md) === '' || ! str_starts_with(ltrim($md), '#')) {
            throw new RuntimeException('That style doesn’t have a DESIGN.md.');
        }
        $entry = $this->libraryEntry($slug);

        return [
            'slug' => $slug,
            'name' => (string) ($entry['name'] ?? $this->titleFromMarkdown($md) ?? ucfirst($slug)),
            'nickname' => (string) ($entry['nickname'] ?? ''),
            'host' => isset($entry['host']) ? (string) $entry['host'] : null,
            'industry' => $entry['industry'] ?? null,
            'theme' => $entry['theme'] ?? null,
            'colors' => array_values(array_filter((array) ($entry['colors'] ?? []), 'is_array')),
            'markdown' => mb_substr($md, 0, 30000),
            'style_url' => $base,
            'design_md_url' => "$base/design.md",
        ];
    }

    private function get(string $url, bool $required): ?string
    {
        $r = Http::timeout(25)->withHeaders(['Accept' => 'text/markdown, text/plain, */*', 'User-Agent' => 'REDOLIO design-reference importer'])
            ->withOptions(['allow_redirects' => false])->get($url);
        if (! $r->successful()) {
            if ($required) {
                throw new RuntimeException($r->status() === 404 ? 'That GradientDeck style could not be found.' : 'GradientDeck isn’t reachable right now. Please try again shortly.');
            }

            return null;
        }
        if (strlen($r->body()) > self::MAX_BYTES) {
            throw new RuntimeException('That DESIGN.md is unexpectedly large.');
        }

        return $r->body();
    }

    /** The library index carries each style's source website, so a screenshot of the inspiring site can be shown. */
    private function libraryEntry(string $slug): array
    {
        $library = Cache::remember('gradientdeck:library', now()->addDay(), function () {
            $r = Http::timeout(30)->withOptions(['allow_redirects' => false])->get('https://'.self::HOST.'/library.json');

            return $r->successful() && is_array($r->json()) ? collect($r->json())->map(fn ($e) => [
                'slug' => $e['slug'] ?? null, 'name' => $e['name'] ?? null, 'nickname' => $e['nickname'] ?? null, 'host' => $e['host'] ?? null,
                'industry' => $e['industry'] ?? null, 'theme' => $e['theme'] ?? null, 'colors' => $e['colors'] ?? [],
            ])->filter(fn ($e) => $e['slug'])->keyBy('slug')->all() : [];
        });

        return $library[$slug] ?? [];
    }

    private function titleFromMarkdown(string $md): ?string
    {
        return preg_match('/^#\s*(.+?)\s*(?:—|-|–)\s*Style Reference/mi', $md, $m) ? trim($m[1]) : null;
    }
}
