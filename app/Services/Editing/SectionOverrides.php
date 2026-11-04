<?php

namespace App\Services\Editing;

use App\Services\Blueprint\BlueprintFonts;
use Dom\Element;
use Dom\HTMLDocument;

/**
 * Per-section presentation choices a visitor may make beyond wording: text size and typeface, a link on text or a
 * picture, the width of the content container and the share of a two-column row. They are stored beside a section's
 * props as `overrides` — never inside them — so the registry contract for the content itself is untouched.
 *
 *   overrides = {
 *     fields: { "<prop path>": { size: 12..120, font: "<approved id>", href: "<safe link>" } },
 *     layout: { width: narrow|standard|wide|full, split: 30..70 }
 *   }
 *
 * Everything is a closed set (numbers in a range, ids from the approved font list, links through the same safe-link
 * rule as content), and is applied to the rendered section by DOM — a user can never inject markup or CSS.
 */
class SectionOverrides
{
    public const SIZE_MIN = 12;

    public const SIZE_MAX = 120;

    public const SPLIT_MIN = 30;

    public const SPLIT_MAX = 70;

    /** Container widths. `standard` means "the design's own width" and is stored as the absence of a value. */
    public const WIDTHS = ['narrow' => '760px', 'wide' => '1360px', 'full' => '100%'];

    /** Same rule ContentValidator applies to every link in the page. */
    private const SAFE_HREF = '/^(#[\w-]*|\/[^\s]*|https:\/\/[^\s]+|mailto:[^\s]+|tel:[+\d\s()-]+)$/i';

    /** Two-column rows whose column shares the layout control can change. */
    private const SPLIT_SECTIONS = ['hero' => ['split', 'image-left', 'product-showcase', 'editorial-asymmetric', 'typography-led'], 'split-content' => ['image-right'], 'cta' => ['split', 'minimal'], 'contact-form' => ['split-contact']];

    public function __construct(private readonly BlueprintFonts $fonts) {}

    public function supportsSplit(string $componentId, string $variantId): bool
    {
        return in_array($variantId, self::SPLIT_SECTIONS[$componentId] ?? [], true);
    }

    /** @return array<int, array{id: string, label: string}> the typefaces a user may choose (REDO's approved, self-hosted list). */
    public function fontChoices(): array
    {
        $out = [['id' => 'sans', 'label' => 'Clean sans-serif (system)'], ['id' => 'serif', 'label' => 'Classic serif (system)']];
        foreach (config('blueprint_fonts') as $id => $f) {
            $out[] = ['id' => $id, 'label' => $f['family']];
        }

        return $out;
    }

    public function validHref(string $href): bool
    {
        return mb_strlen($href) <= 500 && ! str_starts_with($href, '//') && (bool) preg_match(self::SAFE_HREF, $href); // '//host' would be a protocol-relative link to another site
    }

    /** @return array{size?: int, font?: string, href?: string} */
    public function cleanFieldStyle(array $in): array
    {
        $out = [];
        if (isset($in['size']) && $in['size'] !== '') {
            $n = (int) $in['size'];
            if ($n < self::SIZE_MIN || $n > self::SIZE_MAX) {
                throw new SpecEditException('Text size must be between '.self::SIZE_MIN.' and '.self::SIZE_MAX.' px.');
            }
            $out['size'] = $n;
        }
        if (! empty($in['font']) && $in['font'] !== 'default') {
            if (! $this->fonts->isApproved((string) $in['font'])) {
                throw new SpecEditException('Pick a font from the list.');
            }
            $out['font'] = (string) $in['font'];
        }

        return $out;
    }

    // ---------------------------------------------------------------- rendering

    /** Font ids a set of sections asks for (so their @font-face rules are emitted). @return string[] */
    public function fontIds(array $sections): array
    {
        $ids = [];
        foreach ($sections as $s) {
            foreach ($s['overrides']['fields'] ?? [] as $o) {
                ! empty($o['font']) && $ids[] = $o['font'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Apply a section's overrides to its rendered HTML. A section with none is returned untouched, byte for byte, so
     * pages that were never customised render exactly as before.
     *
     * @param  array<string, mixed>  $section  instance with `props` and optionally `overrides`
     */
    public function apply(string $html, array $section): string
    {
        $o = $section['overrides'] ?? null;
        if (! is_array($o) || (empty($o['fields']) && empty($o['layout']))) {
            return $html;
        }
        $doc = HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
        $root = $doc->querySelector('[data-section]');
        if (! $root) {
            return $html;
        }

        $this->applyLayout($root, (array) ($o['layout'] ?? []));
        $claimed = [];
        foreach ((array) ($o['fields'] ?? []) as $path => $style) {
            $value = $this->valueAt($section['props'] ?? [], (string) $path);
            $el = is_array($value) ? $this->findByShape($root, $value, $claimed) : $this->findText($root, (string) $value, $claimed);
            if (! $el) {
                continue; // nothing on the page to style (e.g. an empty optional field)
            }
            $claimed[] = $el;
            $this->applyStyle($el, (array) $style, is_array($value) && isset($value['asset_id']));
        }

        return $doc->body->innerHTML;
    }

    private function applyLayout(Element $root, array $layout): void
    {
        $css = [];
        if (isset(self::WIDTHS[$layout['width'] ?? ''])) {
            $css[] = '--container:'.self::WIDTHS[$layout['width']];
        }
        if (isset($layout['split']) && $layout['split'] >= self::SPLIT_MIN && $layout['split'] <= self::SPLIT_MAX) {
            $a = (int) $layout['split'];
            $css[] = '--cols:'.$a.'fr '.(100 - $a).'fr';
            $css[] = '--ed-text:auto;--ed-aside:auto'; // the editorial hero's 12-column placement collapses to two flowing columns
        }
        if ($css) {
            $root->setAttribute('style', trim(($root->getAttribute('style') ?? '').';'.implode(';', $css), ';'));
        }
    }

    private function applyStyle(Element $el, array $style, bool $isImage): void
    {
        $css = [];
        if (! empty($style['size'])) {
            $n = (int) $style['size'];
            $min = max(self::SIZE_MIN, (int) round($n * 0.62));
            // The chosen size on a desktop screen; it scales down smoothly on narrow screens, never below a readable floor.
            $css[] = "font-size:clamp({$min}px,".round($n / 12, 2).'vw,'.$n.'px)';
        }
        if (! empty($style['font']) && $this->fonts->isApproved($style['font'])) {
            $css[] = 'font-family:'.$this->fonts->stack($style['font']);
        }
        if ($css) {
            $el->setAttribute('style', trim(($el->getAttribute('style') ?? '').';'.implode(';', $css), ';'));
        }
        $href = (string) ($style['href'] ?? '');
        if ($href !== '' && $this->validHref($href) && ! ($el->tagName === 'A' || $el->closest('a'))) {
            $a = $el->ownerDocument->createElement('a');
            $a->setAttribute('href', $href);
            $a->setAttribute('class', 'bp-textlink');
            if (str_starts_with(strtolower($href), 'https://')) {
                $a->setAttribute('rel', 'noopener');
            }
            if ($isImage) { // wrap the picture itself
                $el->parentNode->replaceChild($a, $el);
                $a->appendChild($el);
            } else { // wrap the words, so the element keeps its own tag and style
                while ($el->firstChild) {
                    $a->appendChild($el->firstChild);
                }
                $el->appendChild($a);
            }
        }
    }

    // ---------------------------------------------------------------- matching (mirrors the editor bridge, so what you click is what is styled)

    private function norm(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    private function valueAt(array $props, string $path): mixed
    {
        $cur = $props;
        foreach (explode('.', $path) as $seg) {
            if (! is_array($cur) || ! array_key_exists($seg, $cur)) {
                return '';
            }
            $cur = $cur[$seg];
        }

        return $cur;
    }

    private function findText(Element $root, string $value, array $claimed): ?Element
    {
        $want = $this->norm($value);
        if ($want === '') {
            return null;
        }
        foreach ($root->querySelectorAll('*') as $el) {
            if (in_array($el, $claimed, true) || in_array(strtolower($el->tagName), ['script', 'style', 'svg'], true) || $el->closest('a.bp-btn') || ! $this->isLeaf($el)) {
                continue;
            }
            if ($this->norm($el->textContent) === $want) {
                return $el;
            }
        }

        return null;
    }

    /** A button ({label, href}) or a picture ({asset_id, alt}). */
    private function findByShape(Element $root, array $value, array $claimed): ?Element
    {
        if (isset($value['label'])) {
            $want = $this->norm((string) $value['label']);
            foreach ($root->querySelectorAll('a') as $a) {
                if (! in_array($a, $claimed, true) && $this->norm($a->textContent) === $want) {
                    return $a;
                }
            }

            return null;
        }
        $alt = (string) ($value['alt'] ?? '');
        $visuals = iterator_to_array($root->querySelectorAll('.bp-visual, .bp-mock, .bp-hero__art, .bp-full__art'));
        foreach ($visuals as $v) {
            if (! in_array($v, $claimed, true) && $alt !== '' && $v->getAttribute('aria-label') === $alt) {
                return $v;
            }
        }
        foreach ($visuals as $v) {
            if (! in_array($v, $claimed, true)) {
                return $v;
            }
        }

        return null;
    }

    private function isLeaf(Element $el): bool
    {
        foreach ($el->childNodes as $c) {
            if ($c->nodeType === XML_ELEMENT_NODE && strtolower($c->nodeName) !== 'br') {
                return false;
            }
        }

        return true;
    }
}
