<?php

return [
    'templates' => ['smb_default', 'editorial', 'minimal', 'modern_corporate', 'creative_studio', 'premium_luxury', 'commerce_energetic'],
    'default_template' => 'smb_default',

    'sections' => ['value_props', 'offerings', 'trust', 'testimonials', 'faq', 'contact'],

    'cta_actions' => ['contact', 'booking', 'inquiry', 'scroll_to_section'],
    'default_cta_action' => 'contact',

    'visual_tones' => ['professional', 'warm', 'premium', 'bold', 'minimal', 'playful'],
    'default_visual_tone' => 'professional',

    'typography' => ['modern_sans', 'editorial_serif', 'friendly_rounded', 'technical_mono'],
    'default_typography' => 'modern_sans',

    'spacing_scales' => ['compact', 'balanced', 'spacious'],
    'default_spacing_scale' => 'balanced',

    'button_styles' => ['solid', 'outline', 'rounded'],
    'default_button_style' => 'solid',

    'color_tokens' => [
        'slate', 'ink', 'cream', 'sand', 'forest', 'ocean', 'clay', 'plum', 'amber', 'white', 'black',
    ],
    'default_color_tokens' => [
        'primary' => 'ink',
        'secondary' => 'slate',
        'accent' => 'amber',
        'background' => 'white',
        'text' => 'ink',
    ],

    'primary_goals' => ['lead', 'booking', 'purchase', 'inquiry', 'awareness'],
    'default_primary_goal' => 'inquiry',

    // A section item with no genuinely relevant real/stock/AI-generated
    // image may use one of these instead of going bare — curated so every
    // icon has a real, hand-authored outline SVG in <x-icon> (components/
    // icon.blade.php). Never let the model invent an icon name.
    'icons' => [
        'shield-check', 'chart-bar', 'users', 'heart', 'star', 'clock', 'map-pin', 'phone',
        'mail', 'cog', 'leaf', 'home', 'briefcase', 'award', 'trending-up', 'lightbulb',
        'target', 'handshake', 'rocket', 'lock-closed', 'check-circle', 'sparkles', 'globe', 'calendar',
    ],

    // Design Trend Intelligence (design_patterns table).
    'pattern_types' => ['layout', 'typography', 'color', 'component', 'hero', 'interaction'],
    'pattern_directions' => ['general', 'editorial', 'minimal', 'modern_corporate', 'creative_studio', 'premium_luxury', 'commerce_energetic'],
    'pattern_source_domains' => ['siteinspire.com', 'details.so', 'dribbble.com', 'aura.build'],
];
