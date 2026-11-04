<?php

/*
 * Trusted section-component registry (PRD v1.5 section_component_registry).
 * component_id + variant_id -> Blade view + typed prop schema. This is the
 * ONLY way a blueprint or DesignSpec can reference markup: unknown components,
 * variants and props are rejected, and model output never selects a view path.
 *
 * Only variants with a real, rendered implementation are registered; the PRD's
 * full 20-type target grows here as variants are built and QA'd.
 *
 * Prop types: string, text, cta{label,href}, image{asset_id,alt}, icon, bool,
 * enum, strlist, list(items=>schema, min, max). 'required' defaults to false.
 */

$str = fn (bool $req = false, int $max = 120) => ['type' => 'string', 'required' => $req, 'max' => $max];
$text = fn (bool $req = false) => ['type' => 'text', 'required' => $req, 'max' => 800];
$cta = fn (bool $req = false) => ['type' => 'cta', 'required' => $req];
$image = ['type' => 'image', 'required' => false];

$link = ['type' => 'cta', 'required' => true];
$nav = [
    'brand' => $str(true, 60),
    'links' => ['type' => 'list', 'required' => true, 'min' => 1, 'max' => 7, 'items' => ['label' => $str(true, 30), 'href' => ['type' => 'href', 'required' => true]]],
    'cta' => $cta(),
    'logo' => $image, 'logo_only' => ['type' => 'bool', 'required' => false],
];
$hero = [
    'eyebrow' => $str(false, 80), 'headline' => $str(true, 140), 'subheadline' => $text(),
    'primary_cta' => $cta(true), 'secondary_cta' => $cta(), 'image' => $image,
];
$gallery = [
    'heading' => $str(true), 'subheading' => $text(),
    'items' => ['type' => 'list', 'required' => true, 'min' => 2, 'max' => 9, 'items' => ['title' => $str(true, 80), 'caption' => $str(false, 160), 'image' => $image]],
];
$testimonials = [
    'heading' => $str(true),
    'items' => ['type' => 'list', 'required' => true, 'min' => 1, 'max' => 4, 'items' => ['quote' => $text(true), 'name' => $str(true, 60), 'role' => $str(false, 80)]],
];
$services = [
    'heading' => $str(true), 'subheading' => $text(),
    'items' => ['type' => 'list', 'required' => true, 'min' => 2, 'max' => 6, 'items' => ['title' => $str(true, 70), 'description' => $text(true), 'meta' => $str(false, 60), 'image' => $image, 'cta' => $cta()]],
];
$features = [
    'heading' => $str(true), 'subheading' => $text(),
    'items' => ['type' => 'list', 'required' => true, 'min' => 3, 'max' => 6, 'items' => ['title' => $str(true, 70), 'description' => $text(true), 'icon' => ['type' => 'icon', 'required' => false]]],
];
$footer = [
    'brand' => $str(true, 60), 'tagline' => $str(false, 160),
    'columns' => ['type' => 'list', 'required' => false, 'max' => 4, 'items' => [
        'title' => $str(true, 40),
        'links' => ['type' => 'list', 'required' => true, 'max' => 6, 'items' => ['label' => $str(true, 40), 'href' => ['type' => 'href', 'required' => true]]],
    ]],
    'legal' => $str(false, 160),
];

return [
    'navigation' => ['variants' => [
        'minimal' => ['view' => 'blueprints.sections.navigation.minimal', 'props' => $nav],
        'floating' => ['view' => 'blueprints.sections.navigation.floating', 'props' => $nav],
        'simple' => ['view' => 'blueprints.sections.navigation.simple', 'props' => $nav],
        'product' => ['view' => 'blueprints.sections.navigation.product', 'props' => $nav],
    ]],
    'hero' => ['variants' => [
        'split' => ['view' => 'blueprints.sections.hero.split', 'anchor' => 'top', 'props' => $hero],
        'product-showcase' => ['view' => 'blueprints.sections.hero.product-showcase', 'anchor' => 'top', 'props' => $hero],
        'aerial-fullscreen' => ['view' => 'blueprints.sections.hero.aerial-fullscreen', 'anchor' => 'top', 'props' => $hero],
        'editorial-asymmetric' => ['view' => 'blueprints.sections.hero.editorial-asymmetric', 'anchor' => 'top', 'props' => $hero],
        'typography-led' => ['view' => 'blueprints.sections.hero.typography-led', 'anchor' => 'top', 'props' => $hero],
        'centered' => ['view' => 'blueprints.sections.hero.centered', 'anchor' => 'top', 'props' => $hero],
        'image-left' => ['view' => 'blueprints.sections.hero.image-left', 'anchor' => 'top', 'props' => $hero],
        'banner-left' => ['view' => 'blueprints.sections.hero.banner-left', 'anchor' => 'top', 'props' => $hero],
        'banner-right' => ['view' => 'blueprints.sections.hero.banner-right', 'anchor' => 'top', 'props' => $hero],
        'fullscreen-left' => ['view' => 'blueprints.sections.hero.fullscreen-left', 'anchor' => 'top', 'props' => $hero],
        'fullscreen-right' => ['view' => 'blueprints.sections.hero.fullscreen-right', 'anchor' => 'top', 'props' => $hero],
        'bold-left' => ['view' => 'blueprints.sections.hero.bold-left', 'anchor' => 'top', 'props' => $hero],
        'bold-right' => ['view' => 'blueprints.sections.hero.bold-right', 'anchor' => 'top', 'props' => $hero],
    ]],
    'trust-strip' => ['variants' => [
        'logos' => ['view' => 'blueprints.sections.trust-strip.logos', 'props' => [
            'heading' => $str(false),
            'items' => ['type' => 'list', 'required' => true, 'min' => 3, 'max' => 8, 'items' => ['name' => $str(true, 40)]],
        ]],
    ]],
    'statistics' => ['variants' => [
        'four-column' => ['view' => 'blueprints.sections.statistics.four-column', 'props' => [
            'heading' => $str(false),
            'items' => ['type' => 'list', 'required' => true, 'min' => 2, 'max' => 4, 'items' => ['value' => $str(true, 24), 'label' => $str(true, 60)]],
        ]],
    ]],
    'features' => ['variants' => [
        'bento' => ['view' => 'blueprints.sections.features.bento', 'anchor' => 'features', 'props' => $features],
        'icon-grid' => ['view' => 'blueprints.sections.features.icon-grid', 'anchor' => 'features', 'props' => $features],
    ]],
    'gallery' => ['variants' => [
        'featured-grid' => ['view' => 'blueprints.sections.gallery.featured-grid', 'anchor' => 'gallery', 'props' => $gallery],
        'edge-to-edge-grid' => ['view' => 'blueprints.sections.gallery.edge-to-edge-grid', 'anchor' => 'gallery', 'props' => $gallery],
    ]],
    'process' => ['variants' => [
        'numbered-steps' => ['view' => 'blueprints.sections.process.numbered-steps', 'anchor' => 'process', 'props' => [
            'heading' => $str(true), 'subheading' => $text(),
            'items' => ['type' => 'list', 'required' => true, 'min' => 3, 'max' => 6, 'items' => ['title' => $str(true, 70), 'description' => $text(true)]],
        ]],
    ]],
    'pricing' => ['variants' => [
        'simple' => ['view' => 'blueprints.sections.pricing.simple', 'anchor' => 'pricing', 'props' => [
            'heading' => $str(true), 'subheading' => $text(),
            'items' => ['type' => 'list', 'required' => true, 'min' => 1, 'max' => 3, 'items' => [
                'name' => $str(true, 40), 'price' => $str(true, 24), 'period' => $str(false, 24), 'description' => $text(),
                'features' => ['type' => 'strlist', 'required' => false, 'max' => 8],
                'cta' => $cta(), 'highlighted' => ['type' => 'bool', 'required' => false],
            ]],
        ]],
    ]],
    'faq' => ['variants' => [
        'accordion' => ['view' => 'blueprints.sections.faq.accordion', 'anchor' => 'faq', 'props' => [
            'heading' => $str(true),
            'items' => ['type' => 'list', 'required' => true, 'min' => 2, 'max' => 8, 'items' => ['question' => $str(true, 160), 'answer' => $text(true)]],
        ]],
    ]],
    'cta' => ['variants' => [
        'split' => ['view' => 'blueprints.sections.cta.split', 'anchor' => 'get-started', 'props' => ['heading' => $str(true, 140), 'body' => $text(), 'primary_cta' => $cta(true), 'secondary_cta' => $cta()]],
        'minimal' => ['view' => 'blueprints.sections.cta.minimal', 'anchor' => 'get-started', 'props' => ['heading' => $str(true, 140), 'body' => $text(), 'primary_cta' => $cta(true), 'secondary_cta' => $cta()]],
        'full-width-bold' => ['view' => 'blueprints.sections.cta.full-width-bold', 'anchor' => 'get-started', 'props' => ['heading' => $str(true, 140), 'body' => $text(), 'primary_cta' => $cta(true), 'secondary_cta' => $cta()]],
    ]],
    'footer' => ['variants' => [
        'product-footer' => ['view' => 'blueprints.sections.footer.product-footer', 'props' => $footer],
        'three-column' => ['view' => 'blueprints.sections.footer.three-column', 'props' => $footer],
        'minimal' => ['view' => 'blueprints.sections.footer.minimal', 'props' => $footer],
    ]],
    'services' => ['variants' => [
        'image-cards' => ['view' => 'blueprints.sections.services.image-cards', 'anchor' => 'services', 'props' => $services],
        'editorial-list' => ['view' => 'blueprints.sections.services.editorial-list', 'anchor' => 'services', 'props' => $services],
        'icon-grid' => ['view' => 'blueprints.sections.services.icon-grid', 'anchor' => 'services', 'props' => $services],
    ]],
    'testimonials' => ['variants' => [
        'cards' => ['view' => 'blueprints.sections.testimonials.cards', 'anchor' => 'testimonials', 'props' => $testimonials],
        'editorial-quotes' => ['view' => 'blueprints.sections.testimonials.editorial-quotes', 'anchor' => 'testimonials', 'props' => $testimonials],
    ]],
    'contact-form' => ['variants' => [
        'split-contact' => ['view' => 'blueprints.sections.contact-form.split-contact', 'anchor' => 'contact', 'props' => [
            'heading' => $str(true), 'body' => $text(),
            'fields' => ['type' => 'list', 'required' => true, 'min' => 2, 'max' => 8, 'items' => [
                'name' => $str(true, 30), 'label' => $str(true, 40),
                'type' => ['type' => 'enum', 'required' => true, 'values' => ['text', 'email', 'tel', 'textarea']],
                'required' => ['type' => 'bool', 'required' => false],
            ]],
            'submit_label' => $str(true, 40),
            'form_action' => ['type' => 'mailto', 'required' => false],
            'contact_details' => ['type' => 'list', 'required' => false, 'max' => 5, 'items' => ['label' => $str(true, 30), 'value' => $str(true, 100)]],
        ]],
    ]],
    'schedule' => ['variants' => [
        'weekly-grid' => ['view' => 'blueprints.sections.schedule.weekly-grid', 'anchor' => 'schedule', 'props' => [
            'heading' => $str(true), 'subheading' => $text(),
            'days' => ['type' => 'list', 'required' => true, 'min' => 3, 'max' => 7, 'items' => [
                'day' => $str(true, 20),
                'slots' => ['type' => 'list', 'required' => true, 'min' => 1, 'max' => 4, 'items' => ['time' => $str(true, 24), 'label' => $str(true, 60)]],
            ]],
        ]],
    ]],
    'product-grid' => ['variants' => [
        'standard-grid' => ['view' => 'blueprints.sections.product-grid.standard-grid', 'anchor' => 'products', 'props' => [
            'heading' => $str(true), 'subheading' => $text(),
            'items' => ['type' => 'list', 'required' => true, 'min' => 2, 'max' => 8, 'items' => [
                'title' => $str(true, 70), 'description' => $text(), 'price' => $str(false, 24), 'badge' => $str(false, 24), 'image' => $image, 'cta' => $cta(),
            ]],
        ]],
    ]],
    'split-content' => ['variants' => [
        'image-right' => ['view' => 'blueprints.sections.split-content.image-right', 'anchor' => 'equipment', 'props' => [
            'eyebrow' => $str(false, 60), 'heading' => $str(true), 'body' => $text(true),
            'bullets' => ['type' => 'strlist', 'required' => false, 'max' => 6], 'cta' => $cta(), 'image' => $image,
        ]],
    ]],
];
