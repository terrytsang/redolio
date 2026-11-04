<?php

namespace App\Services\Blueprint;

use App\Models\AnalysisRun;
use Illuminate\Support\Str;

/**
 * Deterministically maps already-validated, evidence-linked content (the legacy
 * validated DesignSpec + Website DNA + the real asset inventory) into a
 * blueprint's content slots. No model call and no new copy: anything without
 * a verified source is left out and reported, never invented. Required
 * sections that cannot be filled are omitted and flagged (status "partial").
 */
class ContentMappingService
{
    /** Short UI labels for navigation — navigation chrome, not business claims. */
    private const NAV_LABELS = [
        'features' => 'Features', 'services' => 'Services', 'process' => 'Process', 'gallery' => 'Gallery', 'pricing' => 'Pricing',
        'schedule' => 'Schedule', 'testimonials' => 'Testimonials', 'faq' => 'FAQ', 'contact-form' => 'Contact',
    ];

    private const REAL_IMAGE_TYPES = ['hero_image', 'portfolio_image', 'team_portrait', 'profile_portrait'];

    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly ContentValidator $validator,
    ) {}

    /**
     * @return array{content: array, bindings: array, asset_bindings: array, omitted: array, forms: array, evidence_ids: string[], asset_ids: string[], required_complete: bool, page: array}
     */
    public function map(AnalysisRun $run, array $blueprint, array $spec, array $dna): array
    {
        $ctx = new class
        {
            public array $content = [];

            public array $bindings = [];

            public array $assetBindings = [];

            public array $omitted = [];

            public array $evidence = [];

            public array $assets = [];

            public array $forms = [];
        };

        $assets = $run->websiteAssets()->get();
        $validKeys = $assets->pluck('asset_key')->all();
        $sectionsByKey = collect($spec['sections'] ?? [])->keyBy('section_key');
        $contact = $this->contactDetails($spec['footer']['contact_text'] ?? null);
        $trustTypes = array_column((array) ($dna['trust_signals'] ?? []), 'type');

        // The hero image: the site's OWN hero/banner photograph wins over whichever image the model happened to pick.
        $heroKey = $this->heroAssetKey($assets, $spec['hero']['image_asset_key'] ?? null, $validKeys);

        $body = [];
        foreach ($blueprint['section_sequence'] as $s) {
            $body[$s['instance_id']] = $s;
        }

        // Pass 1 — body sections (everything the chrome links to).
        foreach ($blueprint['section_sequence'] as $s) {
            if (in_array($s['component_id'], ['navigation', 'hero', 'cta', 'footer'], true)) {
                continue;
            }
            [$raw, $sources, $reason] = $this->bodySection($s, $spec, $sectionsByKey, $dna, $trustTypes, $assets, $contact, $validKeys, $heroKey);
            $this->accept($ctx, $s, $raw, $sources, $reason);
        }

        // Anchors of sections that actually made it onto the page.
        $present = [];
        foreach ($blueprint['section_sequence'] as $s) {
            if (isset($ctx->content[$s['instance_id']])) {
                $present[$s['component_id']] ??= $this->registry->anchor($s['component_id'], $s['variant_id']);
            }
        }
        $navLinks = [];
        foreach (self::NAV_LABELS as $component => $label) {
            if (isset($present[$component]) && count($navLinks) < 6) {
                $navLinks[] = ['label' => $label, 'href' => '#'.$present[$component]];
            }
        }
        $navLinks = $navLinks ?: [['label' => 'Home', 'href' => '#top']];

        $primaryHref = $this->ctaTarget($spec['hero']['primary_cta_action'] ?? 'contact', $present, $contact, $navLinks);
        $secondaryHref = $navLinks[0]['href'];

        // Pass 2 — chrome.
        foreach ($blueprint['section_sequence'] as $s) {
            $raw = $sources = $reason = null;
            switch ($s['component_id']) {
                case 'navigation':
                    $cta = $this->cta($spec['footer']['cta_label'] ?? $spec['hero']['primary_cta_label'] ?? null, $primaryHref);
                    $raw = ['brand' => $spec['business_name'] ?? '', 'links' => $navLinks, 'cta' => $cta];
                    // The site's real logo, when one was found: wide wordmarks stand alone, compact marks sit beside the name.
                    $logo = $assets->first(fn ($a) => $a->asset_type === 'logo' && $a->stored_path && in_array($a->asset_key, $validKeys, true));
                    if ($logo) {
                        $raw['logo'] = ['asset_id' => $logo->asset_key, 'alt' => $spec['business_name'] ?? 'Logo'];
                        $raw['logo_only'] = $logo->width && $logo->height && ($logo->width / max(1, $logo->height)) >= 2.2;
                    }
                    $sources = ['brand' => 'spec.business_name', 'links' => 'derived:sections-present', 'cta' => 'spec.footer.cta_label', 'logo' => 'assets[logo]'];
                    break;
                case 'hero':
                    $hero = $spec['hero'] ?? [];
                    $raw = [
                        'eyebrow' => $hero['eyebrow'] ?? null, 'headline' => $hero['headline'] ?? '', 'subheadline' => $hero['subheadline'] ?? null,
                        'primary_cta' => $this->cta($hero['primary_cta_label'] ?? null, $primaryHref),
                        'secondary_cta' => $this->cta($hero['secondary_cta_label'] ?? null, $secondaryHref),
                    ];
                    $sources = ['headline' => 'spec.hero.headline', 'subheadline' => 'spec.hero.subheadline', 'primary_cta' => 'spec.hero.primary_cta_label'];
                    if ($heroKey) {
                        $raw['image'] = ['asset_id' => $heroKey, 'alt' => $this->alt($assets->firstWhere('asset_key', $heroKey), $spec['business_name'] ?? 'Hero image')];
                    }
                    break;
                case 'cta':
                    $contactSection = $sectionsByKey->get('contact');
                    $heading = $contactSection['heading'] ?? null ?: ($spec['footer']['cta_label'] ?? $spec['hero']['primary_cta_label'] ?? '');
                    $raw = [
                        'heading' => $heading, 'body' => $contactSection['body'] ?? $spec['footer']['summary'] ?? null,
                        'primary_cta' => $this->cta($spec['footer']['cta_label'] ?? $spec['hero']['primary_cta_label'] ?? null, $primaryHref),
                    ];
                    $sources = ['heading' => 'spec.sections.contact.heading|spec.footer.cta_label', 'body' => 'spec.sections.contact.body|spec.footer.summary'];
                    break;
                case 'footer':
                    $cols = [['title' => 'Explore', 'links' => $navLinks]];
                    $links = array_values(array_filter([
                        $contact['email'] ? ['label' => $contact['email'], 'href' => 'mailto:'.$contact['email']] : null,
                        $contact['phone'] ? ['label' => $contact['phone'], 'href' => 'tel:'.$contact['phone']] : null,
                    ]));
                    $links && $cols[] = ['title' => 'Contact', 'links' => $links];
                    $raw = ['brand' => $spec['business_name'] ?? '', 'tagline' => $spec['footer']['summary'] ?? null, 'columns' => $cols, 'legal' => '© '.($spec['business_name'] ?? '')];
                    $sources = ['brand' => 'spec.business_name', 'tagline' => 'spec.footer.summary', 'columns' => 'derived:nav+contact'];
                    break;
                default:
                    continue 2;
            }
            $this->accept($ctx, $s, $raw, $sources, null);
        }

        $requiredOmitted = array_filter($ctx->omitted, fn ($o) => $o['required']);

        return [
            'content' => $ctx->content,
            'bindings' => $ctx->bindings,
            'asset_bindings' => $ctx->assetBindings,
            'omitted' => array_values($ctx->omitted),
            'forms' => $ctx->forms,
            'evidence_ids' => array_values(array_unique($ctx->evidence)),
            'asset_ids' => array_values(array_unique($ctx->assets)),
            'required_complete' => $requiredOmitted === [],
            'page' => [
                'title' => $this->fit($spec['business_name'] ?? '', 120),
                'description' => $this->fit($dna['value_proposition']['text'] ?? $dna['business_summary'] ?? null, 160),
            ],
        ];
    }

    /** @return array{0: ?array, 1: array, 2: ?string} raw content, slot sources, omission reason */
    private function bodySection(array $s, array $spec, $sectionsByKey, array $dna, array $trustTypes, $assets, array $contact, array $validKeys, ?string $heroKey = null): array
    {
        $items = fn (string $key) => (array) ($sectionsByKey->get($key)['items'] ?? []);
        $heading = fn (string $key, string $fallback) => $sectionsByKey->get($key)['heading'] ?? null ?: $fallback;
        $body = fn (string $key) => $sectionsByKey->get($key)['body'] ?? null;

        switch ($s['component_id']) {
            case 'services':
                $src = $items('offerings') ?: $items('value_props');
                if (! $src && ! empty($dna['offerings'])) {
                    $src = array_map(fn ($o) => ['title' => $o['name'], 'description' => $o['description'] ?? ''], $dna['offerings']);
                }
                $key = $items('offerings') ? 'offerings' : 'value_props';

                return [[
                    'heading' => $heading($key, 'Services'), 'subheading' => $body($key),
                    'items' => array_map(fn ($i) => [
                        'title' => $i['title'] ?? '', 'description' => $i['description'] ?? '', 'meta' => $i['label'] ?? null,
                        'image' => $this->itemImage($i, $assets, $validKeys),
                    ], $src),
                ], ['items' => "spec.sections.{$key}.items"], $src ? null : 'No services or offerings were found on your site.'];

            case 'features':
                $key = $items('value_props') ? 'value_props' : ($items('trust') ? 'trust' : 'offerings');
                $src = $items($key);

                return [[
                    'heading' => $heading($key, 'Why choose us'), 'subheading' => $body($key),
                    'items' => array_map(fn ($i) => ['title' => $i['title'] ?? '', 'description' => $i['description'] ?? '', 'icon' => $i['icon'] ?? null], $src),
                ], ['items' => "spec.sections.{$key}.items"], $src ? null : 'No feature or benefit content was found on your site.'];

            case 'testimonials':
                $section = $sectionsByKey->get('testimonials');
                $verified = in_array('testimonial', $trustTypes, true) && ! empty($section['source_evidence_ids']);

                return [$verified ? [
                    'heading' => $section['heading'] ?: 'What clients say',
                    'items' => array_map(fn ($i) => ['quote' => $i['description'] ?? '', 'name' => $i['title'] ?? '', 'role' => $i['label'] ?? null], (array) $section['items']),
                ] : null, ['items' => 'spec.sections.testimonials.items'], $verified ? null : 'No verifiable testimonials were found on your site — none are invented.'];

            case 'faq':
                $src = $items('faq');

                return [$src ? [
                    'heading' => $heading('faq', 'Frequently asked questions'),
                    'items' => array_map(fn ($i) => ['question' => $i['title'] ?? '', 'answer' => $i['description'] ?? ''], $src),
                ] : null, ['items' => 'spec.sections.faq.items'], $src ? null : 'No FAQ content was found on your site.'];

            case 'trust-strip':
                $names = array_values(array_map(fn ($t) => ['name' => $t['description']], array_filter((array) ($dna['trust_signals'] ?? []), fn ($t) => $t['type'] === 'client_logo' && mb_strlen($t['description']) <= 40)));

                return [$names ? ['heading' => null, 'items' => $names] : null, ['items' => 'dna.trust_signals[client_logo]'], $names ? null : 'No client names were found on your site.'];

            case 'gallery':
                $real = $assets->filter(fn ($a) => $a->isRealPhoto() && $a->asset_key !== $heroKey)
                    ->sortByDesc('relevance_score')->take(6)->values();

                return [$real->count() >= 2 ? [
                    'heading' => 'Gallery',
                    'items' => $real->map(fn ($a, $i) => ['title' => $this->fit($a->alt_text ?: ($a->nearby_heading ?: 'Image '.($i + 1)), 80), 'image' => ['asset_id' => $a->asset_key, 'alt' => $this->alt($a, 'Image '.($i + 1))]])->all(),
                ] : null, ['items' => 'assets[real photos]'], $real->count() >= 2 ? null : 'Fewer than two real photos were found for a gallery — stock images are not substituted here.'];

            case 'contact-form':
                if (! $contact['email']) {
                    return [null, [], 'No verified contact email was found, so a working contact form could not be set up.'];
                }

                return [[
                    'heading' => $heading('contact', 'Get in touch'), 'body' => $body('contact'),
                    'fields' => [
                        ['name' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
                        ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                        ['name' => 'message', 'label' => 'How can we help?', 'type' => 'textarea', 'required' => true],
                    ],
                    'submit_label' => $spec['hero']['primary_cta_label'] ?? 'Send message',
                    'form_action' => 'mailto:'.$contact['email'],
                    'contact_details' => array_values(array_filter([
                        ['label' => 'Email', 'value' => $contact['email']],
                        $contact['phone'] ? ['label' => 'Phone', 'value' => $contact['phone']] : null,
                    ])),
                ], ['heading' => 'spec.sections.contact.heading', 'form_action' => 'spec.footer.contact_text'], null];

            case 'statistics':
                return [null, [], 'No verified figures (years, clients, projects) were found — none are made up.'];
            case 'pricing':
                return [null, [], 'No pricing was found on your site — prices are never invented.'];
            case 'process':
                return [null, [], 'No step-by-step process was found on your site.'];
            case 'schedule':
                return [null, [], 'No schedule or opening times were found on your site.'];
            case 'split-content':
                return [null, [], 'No supporting copy for this section was found on your site.'];
        }

        return [null, [], 'This section type has no content mapping yet.'];
    }

    private function accept(object $ctx, array $s, ?array $raw, ?array $sources, ?string $reason): void
    {
        $id = $s['instance_id'];
        if ($raw === null) {
            $ctx->omitted[$id] = ['instance_id' => $id, 'required' => ! $s['optional'], 'reason' => $reason ?? 'No content could be mapped.'];

            return;
        }

        $schema = $this->registry->props($s['component_id'], $s['variant_id']);
        $conformed = $this->conform($schema, $raw);
        $errors = $this->validator->validate($schema, $conformed, $id);
        if ($errors) {
            $ctx->omitted[$id] = ['instance_id' => $id, 'required' => ! $s['optional'], 'reason' => 'Source content did not fit this section: '.$errors[0]];

            return;
        }

        $ctx->content[$id] = $conformed;
        $ctx->bindings[$id] = $sources ?? [];
        foreach ($this->collectAssetIds($conformed) as $assetId) {
            $ctx->assetBindings[$id][] = ['slot' => 'image', 'asset_id' => $assetId];
            $ctx->assets[] = $assetId;
        }
        if (! empty($ctx->assetBindings[$id])) {
            $ctx->assetBindings[$id] = array_values(array_unique($ctx->assetBindings[$id], SORT_REGULAR));
        }
    }

    private function collectAssetIds(array $node): array
    {
        $ids = [];
        array_walk_recursive($node, function ($v, $k) use (&$ids) {
            $k === 'asset_id' && $v && $ids[] = $v;
        });

        return $ids;
    }

    /** Trim to schema limits, drop empties, unknown props and unsafe values — never alters meaning. */
    private function conform(array $schema, array $data): array
    {
        $out = [];
        foreach ($schema as $key => $rule) {
            $v = $data[$key] ?? null;
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $v = $this->conformValue($rule, $v);
            if ($v !== null && $v !== [] && $v !== '') {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    private function conformValue(array $rule, mixed $v): mixed
    {
        switch ($rule['type']) {
            case 'string':
            case 'text':
                return is_string($v) ? $this->fit($v, $rule['max'] ?? 200) : null;
            case 'icon':
                return in_array($v, config('design_allowlist.icons'), true) ? $v : null;
            case 'cta':
                return is_array($v) && ! empty($v['label']) && ! empty($v['href']) ? ['label' => $this->fit($v['label'], 50), 'href' => $v['href']] : null;
            case 'strlist':
                return is_array($v) ? array_slice(array_values(array_filter(array_map(fn ($x) => is_string($x) ? $this->fit($x, 160) : null, $v))), 0, $rule['max'] ?? 10) : null;
            case 'list':
                if (! is_array($v)) {
                    return null;
                }
                $items = array_values(array_filter(array_map(fn ($i) => is_array($i) ? $this->conform($rule['items'], $i) : null, $v)));
                // Drop items that lost a required field in conforming, rather than failing the whole list.
                $items = array_values(array_filter($items, function ($i) use ($rule) {
                    foreach ($rule['items'] as $k => $r) {
                        if (($r['required'] ?? false) && ! isset($i[$k])) {
                            return false;
                        }
                    }

                    return true;
                }));

                return array_slice($items, 0, $rule['max'] ?? 12);
            case 'image':
                return is_array($v) && ! empty($v['asset_id']) && ! empty($v['alt']) ? ['asset_id' => (string) $v['asset_id'], 'alt' => $this->fit($v['alt'], 200)] : null;
            default:
                return $v;
        }
    }

    /** Plain text only, cut at a word boundary. */
    private function fit(mixed $text, int $max): ?string
    {
        if (! is_string($text)) {
            return null;
        }
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['<', '>'], ' ', $text))));
        if ($text === '') {
            return null;
        }

        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1), " ,.;:-").'…';
    }

    private function cta(?string $label, string $href): ?array
    {
        return $label ? ['label' => $label, 'href' => $href] : null;
    }

    /**
     * The redesign leads with the site's OWN lead photograph: the first large, landscape, genuinely photographic
     * image on its homepage (insertion order is DOM order). Role labels from the inventory proved unreliable
     * (a flat decorative texture was labelled "hero background"), so they are not used. Near-flat textures and
     * square/portrait graphics are skipped. The model's pick is kept only if it IS that image, or if it
     * deliberately chose a stock/AI fallback.
     *
     * @param  string[]  $validKeys
     */
    private function heroAssetKey($assets, ?string $chosen, array $validKeys): ?string
    {
        $chosenAsset = $chosen && in_array($chosen, $validKeys, true) ? $assets->firstWhere('asset_key', $chosen) : null;
        if ($chosenAsset && ! $chosenAsset->isRealPhoto()) {
            return $chosen; // a deliberate stock/AI fallback
        }

        $lead = $assets->sortBy('id')->first(function ($a) {
            if (! $a->isRealPhoto() || (int) $a->width < 1000 || (int) $a->width < (int) $a->height * 1.2) {
                return false;
            }
            $variation = $a->tonalVariation();

            return $variation === null || $variation >= 20; // unknown (e.g. unreadable) is not penalised
        });

        return $lead?->asset_key ?? ($chosenAsset ? $chosen : null);
    }

    private function alt(?object $asset, string $fallback): string
    {
        return $this->fit($asset?->alt_text ?: ($asset?->nearby_heading ?: $fallback), 200) ?? 'Image';
    }

    private function itemImage(array $item, $assets, array $validKeys): ?array
    {
        $key = $item['asset_key'] ?? null;
        if (! $key || ! in_array($key, $validKeys, true)) {
            return null;
        }

        return ['asset_id' => $key, 'alt' => $this->alt($assets->firstWhere('asset_key', $key), $item['title'] ?? 'Image')];
    }

    /** @return array{email: ?string, phone: ?string} */
    private function contactDetails(?string $text): array
    {
        $email = $phone = null;
        if ($text) {
            preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $e);
            preg_match('/\+?\d[\d\s().\-]{6,}\d/', $text, $p);
            $email = $e[0] ?? null;
            $phone = isset($p[0]) ? trim($p[0]) : null;
        }

        return ['email' => $email, 'phone' => $phone];
    }

    private function ctaTarget(string $action, array $present, array $contact, array $navLinks): string
    {
        if (isset($present['contact-form'])) {
            return '#'.$present['contact-form'];
        }
        if ($contact['email']) {
            return 'mailto:'.$contact['email'];
        }
        if ($contact['phone']) {
            return 'tel:'.preg_replace('/[^\d+]/', '', $contact['phone']);
        }

        return $navLinks[0]['href'];
    }
}
