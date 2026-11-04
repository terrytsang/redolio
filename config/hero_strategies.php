<?php

/*
 * REDO v1.6 hero strategy library (PRD hero_strategy_library). The examples in the PRD are illustrative and are
 * deliberately NOT stored here: copy is always generated from the project's own evidence, never from a template.
 */
return [
    'direct-positioning' => [
        'name' => 'Direct Positioning',
        'purpose' => 'State clearly what the business provides and its primary customer value.',
        'formula' => '[Clear customer value] + [business category or offering]',
        'best_for' => ['b2b', 'professional_services', 'established', 'unclear_messaging', 'supplier', 'manufacturer'],
        'caution' => "Words such as 'trusted' need brand or evidence support; use a neutral alternative if not.",
    ],
    'customer-outcome' => [
        'name' => 'Customer Outcome',
        'purpose' => 'Lead with a meaningful outcome the customer seeks.',
        'formula' => '[Customer outcome] + [clear context in the description]',
        'best_for' => ['service', 'consultancy', 'education', 'solution'],
        'caution' => 'Do not imply guaranteed results.',
    ],
    'product-clarity' => [
        'name' => 'Product Clarity',
        'purpose' => 'Make the primary product or service category immediately obvious.',
        'formula' => '[Primary product category] + [meaningful scope or benefit]',
        'best_for' => ['manufacturer', 'distributor', 'supplier', 'retail', 'specialist_products'],
        'caution' => 'Do not list every SKU or technical specification.',
    ],
    'specialist-expertise' => [
        'name' => 'Specialist Expertise',
        'purpose' => 'Communicate a verified specialist focus or capability.',
        'best_for' => ['specialist', 'technical', 'professional_services', 'niche_supplier'],
        'formula' => '[Specialist capability] + [audience or application]',
        'caution' => "Words such as 'precision' or 'specialist' must be supported by the actual offering.",
    ],
    'brand-led' => [
        'name' => 'Brand-Led',
        'purpose' => 'Use a distinctive brand idea while the description clarifies the actual offer.',
        'formula' => '[Distinctive brand idea] + [plain-language explanatory description]',
        'best_for' => ['creative', 'consumer', 'recognised_brand', 'distinctive_voice'],
        'caution' => 'Never use an abstract slogan alone when visitors cannot identify the business category.',
    ],
];
