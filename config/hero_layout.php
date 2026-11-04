<?php

/*
 * Hero copy rules and component layout constraints (REDO v1.6).
 * Word/character limits are PREFLIGHT HEURISTICS only: the browser-rendered component with its loaded font is
 * authoritative (HeroLayoutValidationService). Line-count defaults are the PRD's preferred design defaults, not hard text limits.
 */
return [
    'headline' => ['min_words' => 4, 'target_max_words' => 10, 'soft_max_words' => 12, 'hard_max_words' => 16, 'target_min_chars' => 25, 'target_max_chars' => 65, 'soft_max_chars' => 80],
    'description' => ['min_words' => 12, 'target_min_words' => 15, 'target_max_words' => 30, 'soft_max_words' => 36, 'target_min_chars' => 90, 'target_max_chars' => 190, 'soft_max_chars' => 230, 'max_sentences' => 2],
    'eyebrow' => ['min_words' => 2, 'max_words' => 8],
    'cta' => ['min_words' => 2, 'max_words' => 5],

    // Phrases that signal a generic or empty hero (editorial gate).
    'generic_openings' => ['welcome to', 'we are', "we're", 'your one-stop solution', 'one-stop solution for all', 'for all your needs'],
    'generic_phrases' => ['all your needs', 'tailored to meet', 'high-quality solutions', 'world-class', 'best-in-class', 'cutting-edge', 'state-of-the-art', 'innovative solutions', 'committed to excellence', 'second to none'],
    'weak_ctas' => ['learn more', 'click here', 'read more', 'submit', 'more', 'continue', 'go', 'here'],

    // Claims that must be evidenced to appear in public copy.
    'hard_claim_words' => ['best', 'leading', 'leader', '#1', 'number one', 'no. 1', 'top', 'premier', 'largest', 'fastest', 'cheapest', 'lowest price', 'award-winning', 'guaranteed', 'guarantee', 'certified', 'accredited', 'only', 'unrivalled', 'unrivaled', 'unmatched'],
    'soft_claim_words' => ['trusted', 'reliable', 'expert', 'specialist', 'precision', 'quality', 'professional', 'innovative', 'experienced'],

    // Preferred H1 line counts per hero variant (PRD preferred_line_count_defaults); description lines are a design default.
    'variants' => [
        'split' => ['family' => 'split_hero', 'h1_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 5], 'description_lines' => ['desktop' => 4, 'tablet' => 5, 'mobile' => 7], 'chars_per_line' => ['desktop' => 22, 'tablet' => 26, 'mobile' => 15]],
        'aerial-fullscreen' => ['family' => 'full_screen_image', 'h1_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 5], 'description_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 6], 'chars_per_line' => ['desktop' => 16, 'tablet' => 18, 'mobile' => 12]],
        'product-showcase' => ['family' => 'product_showcase', 'h1_lines' => ['desktop' => 4, 'tablet' => 5, 'mobile' => 6], 'description_lines' => ['desktop' => 4, 'tablet' => 5, 'mobile' => 7], 'chars_per_line' => ['desktop' => 20, 'tablet' => 24, 'mobile' => 14]],
        'typography-led' => ['family' => 'typography_led', 'h1_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 5], 'description_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 6], 'chars_per_line' => ['desktop' => 20, 'tablet' => 20, 'mobile' => 10]],
        'editorial-asymmetric' => ['family' => 'editorial', 'h1_lines' => ['desktop' => 4, 'tablet' => 5, 'mobile' => 6], 'description_lines' => ['desktop' => 4, 'tablet' => 5, 'mobile' => 7], 'chars_per_line' => ['desktop' => 18, 'tablet' => 20, 'mobile' => 12]],
        'centered' => ['family' => 'centered', 'h1_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 5], 'description_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 6], 'chars_per_line' => ['desktop' => 26, 'tablet' => 22, 'mobile' => 14]],
        'image-left' => ['family' => 'split_hero', 'h1_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 5], 'description_lines' => ['desktop' => 4, 'tablet' => 5, 'mobile' => 7], 'chars_per_line' => ['desktop' => 22, 'tablet' => 26, 'mobile' => 15]],
        'banner-left' => ['family' => 'banner_overlay', 'h1_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 5], 'description_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 6], 'chars_per_line' => ['desktop' => 20, 'tablet' => 22, 'mobile' => 13]],
        'banner-right' => ['family' => 'banner_overlay', 'h1_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 5], 'description_lines' => ['desktop' => 3, 'tablet' => 4, 'mobile' => 6], 'chars_per_line' => ['desktop' => 20, 'tablet' => 22, 'mobile' => 13]],
        'fullscreen-left' => ['family' => 'full_screen_side', 'h1_lines' => ['desktop' => 4, 'tablet' => 4, 'mobile' => 5], 'description_lines' => ['desktop' => 4, 'tablet' => 4, 'mobile' => 6], 'chars_per_line' => ['desktop' => 16, 'tablet' => 22, 'mobile' => 13]],
        'fullscreen-right' => ['family' => 'full_screen_side', 'h1_lines' => ['desktop' => 4, 'tablet' => 4, 'mobile' => 5], 'description_lines' => ['desktop' => 4, 'tablet' => 4, 'mobile' => 6], 'chars_per_line' => ['desktop' => 16, 'tablet' => 22, 'mobile' => 13]],
        'bold-left' => ['family' => 'bold_type', 'h1_lines' => ['desktop' => 3, 'tablet' => 3, 'mobile' => 4], 'description_lines' => ['desktop' => 3, 'tablet' => 3, 'mobile' => 5], 'chars_per_line' => ['desktop' => 14, 'tablet' => 12, 'mobile' => 8]],
        'bold-right' => ['family' => 'bold_type', 'h1_lines' => ['desktop' => 3, 'tablet' => 3, 'mobile' => 4], 'description_lines' => ['desktop' => 3, 'tablet' => 3, 'mobile' => 5], 'chars_per_line' => ['desktop' => 14, 'tablet' => 12, 'mobile' => 8]],
    ],
    'default_variant' => 'split',

    // Copy guidance per variant, passed to the model (PRD layout_guidance).
    'copy_rules' => [
        'split' => 'Short, specific H1 with a concise explanatory paragraph; avoid long technical lists.',
        'aerial-fullscreen' => 'One memorable proposition and minimal supporting copy; ensure sufficient contrast.',
        'editorial-asymmetric' => 'Expressive line breaks and stronger brand voice are allowed, but meaning must stay immediate.',
        'product-showcase' => 'Lead with the product or customer value; keep detailed specifications for adjacent product content.',
        'centered' => 'A single clear proposition; short description; one obvious action.',
        'image-left' => 'Short, specific H1 with a concise explanatory paragraph; the picture leads on the left.',
        'banner-left' => 'One strong proposition over a photo; keep the description short so it stays readable on the image.',
        'banner-right' => 'One strong proposition over a photo; keep the description short so it stays readable on the image.',
        'fullscreen-left' => 'A short headline (it is set very large) and one or two sentences; the picture fills the other side.',
        'fullscreen-right' => 'A short headline (it is set very large) and one or two sentences; the picture fills the other side.',
        'bold-left' => 'A very short, punchy headline (it is set huge); the description explains what it means.',
        'bold-right' => 'A very short, punchy headline (it is set huge); the description explains what it means.',
        'typography-led' => 'Concise, distinctive copy; the description must clarify any abstract headline language.',
    ],

    // Evaluation (PRD copy_evaluation).
    'weights' => ['clarity' => 25, 'audience_relevance' => 20, 'specificity' => 15, 'customer_value' => 15, 'credibility_and_evidence' => 10, 'concision' => 10, 'brand_voice_fit' => 5],
    'thresholds' => ['recommended' => 80, 'needs_review' => 65],

    // Bounded refinement (PRD bounded_refinement).
    'max_copy_refinements' => 2,
    'max_layout_adjustments' => 2,
    'default_options' => 3,
    // Put the recommended hero wording on the first generated REDO automatically (see AutoHeroService).
    'auto_apply' => env('HERO_AUTO_APPLY', true),
];
