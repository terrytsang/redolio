<?php

namespace App\Services\MetaApi;

/**
 * JSON Schema (for the Responses API's `text.format.json_schema`) per
 * reasoning stage, derived from the PRD's `schemas` section. These only
 * need to get the model close to the right shape — App\Services\MetaApi\
 * Validation\* remains the authoritative validator regardless of what a
 * schema-constrained response claims (PRD `structured_output` policy).
 */
class StageSchemas
{
    public static function for(string $stage, array $input = []): array
    {
        return match ($stage) {
            'design_trend_scouting' => self::designTrendScouting(),
            'design_direction_ranking' => self::designDirectionRanking(),
            'design_direction_capture' => self::designDirectionCapture(),
            'design_direction_from_design_md' => self::designDirectionCapture(),
            'visual_qa_screenshot_review' => self::visualQaScreenshotReview(),
            'website_visual_intelligence' => self::visualIntelligence(),
            'website_understanding' => self::websiteDna(),
            'diagnosis' => self::diagnosisReport(),
            'redesign_recommendations' => self::designSpec($input),
            'evaluation' => self::evaluationReport(),
            'next_redo_optional' => self::nextRedoHypothesis(),
            'positioning_profile' => self::positioningProfile(),
            'hero_copy_options' => self::heroCopyOptions(),
            'hero_copy_refine' => self::heroCopyRefine(),
            default => throw new \InvalidArgumentException("No schema for stage [{$stage}]."),
        };
    }

    private static function evidenceRef(): array
    {
        return ['type' => 'array', 'items' => ['type' => 'string']];
    }

    private static function designTrendScouting(): array
    {
        $allow = config('design_allowlist');

        return [
            'name' => 'DesignTrendScouting',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'patterns' => [
                        'type' => 'array',
                        'minItems' => 3,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'pattern_type' => ['type' => 'string', 'enum' => $allow['pattern_types']],
                                'title' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'applicable_directions' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $allow['pattern_directions']]],
                                'source_domains' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $allow['pattern_source_domains']]],
                                'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            ],
                            'required' => ['pattern_type', 'title', 'description', 'applicable_directions', 'source_domains', 'confidence'],
                        ],
                    ],
                    'sources_reachable' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['patterns', 'sources_reachable'],
            ],
        ];
    }

    private static function designDirectionRanking(): array
    {
        return [
            'name' => 'DesignDirectionRanking',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'recommendations' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'direction_key' => ['type' => 'string'],
                            'rank' => ['type' => 'integer'],
                            'score' => ['type' => 'number'],
                            'rationale' => ['type' => 'string'],
                        ],
                        'required' => ['direction_key', 'rank', 'score', 'rationale'],
                    ]],
                    'recommended_direction_keys' => ['type' => 'array', 'maxItems' => 3, 'items' => ['type' => 'string']],
                ],
                'required' => ['recommendations', 'recommended_direction_keys'],
            ],
        ];
    }

    private static function visualQaScreenshotReview(): array
    {
        return [
            'name' => 'VisualQaScreenshotReview',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'findings' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'check' => ['type' => 'string'],
                            'viewport' => ['type' => 'string', 'enum' => ['desktop', 'mobile']],
                            'severity' => ['type' => 'string', 'enum' => ['critical', 'high', 'medium', 'low']],
                            'status' => ['type' => 'string', 'enum' => ['passed', 'failed']],
                            'evidence' => ['type' => 'string'],
                        ],
                        'required' => ['check', 'viewport', 'severity', 'status', 'evidence'],
                    ]],
                    'overall_assessment' => ['type' => 'string'],
                ],
                'required' => ['findings', 'overall_assessment'],
            ],
        ];
    }

    private static function designDirectionCapture(): array
    {
        $allow = config('design_allowlist');

        return [
            'name' => 'DesignDirectionCapture',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'template_key' => ['type' => 'string', 'enum' => $allow['templates']],
                    'family' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'best_for' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'brand_traits' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'color_system' => [
                        'type' => 'object',
                        'properties' => [
                            'background' => ['type' => 'string'],
                            'surface' => ['type' => 'string'],
                            'text' => ['type' => 'string'],
                            'text_muted' => ['type' => 'string'],
                            'accent' => ['type' => 'string'],
                            'accent_contrast' => ['type' => 'string'],
                        ],
                        'required' => ['background', 'surface', 'text', 'text_muted', 'accent', 'accent_contrast'],
                    ],
                    'typography_system' => [
                        'type' => 'object',
                        'properties' => [
                            'display' => ['type' => 'string', 'enum' => ['font-sans', 'font-serif', 'font-mono']],
                            'body' => ['type' => 'string', 'enum' => ['font-sans', 'font-serif', 'font-mono']],
                        ],
                        'required' => ['display', 'body'],
                    ],
                    'layout_compositions' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'hero_patterns' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'section_patterns' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'image_treatment' => ['type' => 'string'],
                    'spacing_system' => ['type' => 'string', 'enum' => ['spacious', 'balanced', 'compact']],
                    'component_theme' => ['type' => 'string'],
                    'responsive_rules' => ['type' => 'string'],
                    'accessibility_rules' => ['type' => 'string'],
                ],
                'required' => [
                    'template_key', 'family', 'description', 'best_for', 'brand_traits', 'color_system',
                    'typography_system', 'layout_compositions', 'hero_patterns', 'section_patterns',
                    'image_treatment', 'spacing_system', 'component_theme', 'responsive_rules', 'accessibility_rules',
                ],
            ],
        ];
    }

    private static function visualIntelligence(): array
    {
        $confidence = ['type' => 'string', 'enum' => ['low', 'medium', 'high']];

        return [
            'name' => 'WebsiteVisualIntelligence',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'visual_profile' => [
                        'type' => 'object',
                        'properties' => [
                            'detected_colors' => ['type' => 'array', 'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'hex' => ['type' => 'string'],
                                    'role' => ['type' => 'string'],
                                    'source' => ['type' => 'string', 'enum' => ['css_declared', 'model_inference']],
                                    'confidence' => $confidence,
                                ],
                                'required' => ['hex', 'role', 'source', 'confidence'],
                            ]],
                            'typography_clues' => ['type' => 'array', 'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'role' => ['type' => 'string'],
                                    'family' => ['type' => 'string'],
                                    'confidence' => $confidence,
                                ],
                                'required' => ['role', 'family', 'confidence'],
                            ]],
                            'layout_patterns' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'image_usage' => ['type' => 'array', 'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'role' => ['type' => 'string'],
                                    'count' => ['type' => 'integer'],
                                ],
                                'required' => ['role', 'count'],
                            ]],
                            'brand_personality' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'design_strengths' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'design_weaknesses' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'unknowns' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['detected_colors', 'typography_clues', 'layout_patterns', 'image_usage', 'brand_personality', 'design_strengths', 'design_weaknesses', 'unknowns'],
                    ],
                    'asset_classifications' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'asset_id' => ['type' => 'string'],
                            'asset_type' => ['type' => 'string'],
                            'business_relevance' => $confidence,
                            'visual_relevance' => $confidence,
                            'recommended_role' => ['type' => 'string'],
                            'preservation_priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'recommended_action' => ['type' => 'string', 'enum' => ['preserve', 'reuse', 'reposition', 'crop', 'replace', 'omit', 'needs_user_review']],
                            'reason' => ['type' => 'string'],
                            'confidence' => $confidence,
                        ],
                        'required' => ['asset_id', 'asset_type', 'business_relevance', 'visual_relevance', 'recommended_role', 'preservation_priority', 'recommended_action', 'reason', 'confidence'],
                    ]],
                ],
                'required' => ['visual_profile', 'asset_classifications'],
            ],
        ];
    }

    private static function websiteDna(): array
    {
        return [
            'name' => 'WebsiteDna',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'business_name' => ['type' => ['string', 'null']],
                    'industry' => ['type' => ['string', 'null']],
                    'business_summary' => ['type' => 'string'],
                    'target_audience' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'segment' => ['type' => 'string'],
                            'needs' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'evidence_ids' => self::evidenceRef(),
                        ],
                        'required' => ['segment', 'needs', 'confidence', 'evidence_ids'],
                    ]],
                    'offerings' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'evidence_ids' => self::evidenceRef(),
                        ],
                        'required' => ['name', 'description', 'evidence_ids'],
                    ]],
                    'value_proposition' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => ['string', 'null']],
                            'evidence_ids' => self::evidenceRef(),
                            'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                        ],
                        'required' => ['text', 'evidence_ids', 'confidence'],
                    ],
                    'tone_of_voice' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'primary_conversion_goal' => ['type' => 'string', 'enum' => ['lead', 'booking', 'purchase', 'inquiry', 'awareness', 'unknown']],
                    'cta_inventory' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'page_url' => ['type' => 'string'],
                            'evidence_ids' => self::evidenceRef(),
                        ],
                        'required' => ['label', 'page_url', 'evidence_ids'],
                    ]],
                    'trust_signals' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['testimonial', 'credential', 'case_study', 'client_logo', 'guarantee', 'contact_detail', 'other']],
                            'description' => ['type' => 'string'],
                            'evidence_ids' => self::evidenceRef(),
                        ],
                        'required' => ['type', 'description', 'evidence_ids'],
                    ]],
                    'observed_facts' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'claim' => ['type' => 'string'],
                            'evidence_ids' => self::evidenceRef(),
                        ],
                        'required' => ['claim', 'evidence_ids'],
                    ]],
                    'inferences' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'claim' => ['type' => 'string'],
                            'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'evidence_ids' => self::evidenceRef(),
                        ],
                        'required' => ['claim', 'confidence', 'evidence_ids'],
                    ]],
                    'uncertainties' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => [
                    'business_name', 'industry', 'business_summary', 'target_audience', 'offerings',
                    'value_proposition', 'tone_of_voice', 'primary_conversion_goal', 'cta_inventory',
                    'trust_signals', 'observed_facts', 'inferences', 'uncertainties',
                ],
            ],
        ];
    }

    private static function diagnosisReport(): array
    {
        $issue = [
            'type' => 'object',
            'properties' => [
                'issue_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'category' => ['type' => 'string', 'enum' => ['positioning', 'audience', 'messaging', 'information_architecture', 'cta', 'trust', 'content', 'mobile', 'accessibility', 'technical', 'other']],
                'severity' => ['type' => 'string', 'enum' => ['high', 'medium', 'low']],
                'problem' => ['type' => 'string'],
                'why_it_matters' => ['type' => 'string'],
                'recommended_solution' => ['type' => 'string'],
                'evidence_ids' => self::evidenceRef(),
                'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'verification_type' => ['type' => 'string', 'enum' => ['observed', 'heuristic', 'model_inference']],
            ],
            'required' => ['issue_id', 'title', 'category', 'severity', 'problem', 'why_it_matters', 'recommended_solution', 'evidence_ids', 'confidence', 'verification_type'],
        ];

        return [
            'name' => 'DiagnosisReport',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'summary' => ['type' => 'string'],
                    'issues' => ['type' => 'array', 'items' => $issue, 'maxItems' => 5],
                    'overall_confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                    'limitations' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['summary', 'issues', 'overall_confidence', 'limitations'],
            ],
        ];
    }

    /**
     * Every enum here is pulled directly from config/design_allowlist.php —
     * the same source DesignSpecValidator checks against — so the schema
     * can never drift from what actually survives validation. Before this,
     * section_key/color_tokens/typography/asset_key were free-form strings
     * in the schema; the model used values outside the allowlist and
     * DesignSpecValidator silently dropped every section as a result
     * (empty sections[], but a full rationale referencing content that was
     * never actually rendered).
     */
    private static function designSpec(array $input = []): array
    {
        $allow = config('design_allowlist');

        // Built from THIS request's real available_assets (never the
        // placeholder allowlist) so the model can name an actual asset_id
        // per hero/item slot instead of a meaningless constant — the fix
        // for images landing on the wrong card (AT-24/image-relevance).
        $realAssetKeys = collect($input['context']['available_assets'] ?? [])
            ->pluck('asset_id')->filter()->values()->all();
        $assetKeyType = ['type' => ['string', 'null'], 'enum' => array_merge([null], $realAssetKeys)];
        $iconType = ['type' => ['string', 'null'], 'enum' => array_merge([null], $allow['icons'])];

        return [
            'name' => 'DesignSpec',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'template_key' => ['type' => 'string', 'enum' => $allow['templates']],
                    'business_name' => ['type' => 'string'],
                    'primary_goal' => ['type' => 'string', 'enum' => $allow['primary_goals']],
                    'brand' => [
                        'type' => 'object',
                        'properties' => [
                            'visual_tone' => ['type' => 'string', 'enum' => $allow['visual_tones']],
                            'color_tokens' => [
                                'type' => 'object',
                                'properties' => [
                                    'primary' => ['type' => 'string', 'enum' => $allow['color_tokens']],
                                    'secondary' => ['type' => 'string', 'enum' => $allow['color_tokens']],
                                    'accent' => ['type' => 'string', 'enum' => $allow['color_tokens']],
                                    'background' => ['type' => 'string', 'enum' => $allow['color_tokens']],
                                    'text' => ['type' => 'string', 'enum' => $allow['color_tokens']],
                                ],
                                'required' => ['primary', 'secondary', 'accent', 'background', 'text'],
                            ],
                            'typography' => ['type' => 'string', 'enum' => $allow['typography']],
                            'spacing_scale' => ['type' => 'string', 'enum' => $allow['spacing_scales']],
                            'button_style' => ['type' => 'string', 'enum' => $allow['button_styles']],
                        ],
                        'required' => ['visual_tone', 'color_tokens', 'typography', 'spacing_scale', 'button_style'],
                    ],
                    'hero' => [
                        'type' => 'object',
                        'properties' => [
                            'eyebrow' => ['type' => ['string', 'null']],
                            'headline' => ['type' => 'string'],
                            'subheadline' => ['type' => 'string'],
                            'primary_cta_label' => ['type' => 'string'],
                            'primary_cta_action' => ['type' => 'string', 'enum' => $allow['cta_actions']],
                            'secondary_cta_label' => ['type' => ['string', 'null']],
                            'image_asset_key' => $assetKeyType,
                        ],
                        'required' => ['eyebrow', 'headline', 'subheadline', 'primary_cta_label', 'primary_cta_action', 'secondary_cta_label', 'image_asset_key'],
                    ],
                    'sections' => [
                        'type' => 'array',
                        'minItems' => 2,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'section_key' => ['type' => 'string', 'enum' => $allow['sections']],
                                'heading' => ['type' => 'string'],
                                'body' => ['type' => ['string', 'null']],
                                'items' => ['type' => 'array', 'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => ['type' => 'string'],
                                        'description' => ['type' => 'string'],
                                        'label' => ['type' => ['string', 'null']],
                                        'asset_key' => $assetKeyType,
                                        'icon' => $iconType,
                                    ],
                                    'required' => ['title', 'description', 'label', 'asset_key', 'icon'],
                                ]],
                                'source_evidence_ids' => self::evidenceRef(),
                                'addresses_issue_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                            ],
                            'required' => ['section_key', 'heading', 'body', 'items', 'source_evidence_ids', 'addresses_issue_ids'],
                        ],
                    ],
                    'footer' => [
                        'type' => 'object',
                        'properties' => [
                            'summary' => ['type' => ['string', 'null']],
                            'contact_text' => ['type' => ['string', 'null']],
                            'cta_label' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['summary', 'contact_text', 'cta_label'],
                    ],
                    'rationale' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'decision' => ['type' => 'string'],
                            'addresses_issue_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['decision', 'addresses_issue_ids'],
                    ]],
                    'design_personality' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'visual_direction' => ['type' => 'string'],
                    'media_strategy' => ['type' => 'string'],
                    'layout_composition' => ['type' => 'string'],
                    'conversion_hierarchy' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'asset_references' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'asset_id' => ['type' => 'string'],
                            'role' => ['type' => 'string', 'enum' => ['hero_portrait', 'logo', 'section_image']],
                            'reason' => ['type' => 'string'],
                        ],
                        'required' => ['asset_id', 'role', 'reason'],
                    ]],
                ],
                'required' => [
                    'template_key', 'business_name', 'primary_goal', 'brand', 'hero', 'sections', 'footer', 'rationale',
                    'design_personality', 'visual_direction', 'media_strategy', 'layout_composition', 'conversion_hierarchy', 'asset_references',
                ],
            ],
        ];
    }

    private static function evaluationReport(): array
    {
        return [
            'name' => 'EvaluationReport',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'items' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'issue_id' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['addressed', 'partially_addressed', 'not_addressed']],
                            'rationale' => ['type' => 'string'],
                            'checks' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['issue_id', 'status', 'rationale', 'checks'],
                    ]],
                    'overall_note' => ['type' => 'string'],
                ],
                'required' => ['items', 'overall_note'],
            ],
        ];
    }

    private static function nextRedoHypothesis(): array
    {
        return [
            'name' => 'NextRedoHypothesis',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'hypothesis' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                    'suggested_change' => ['type' => 'string'],
                    'success_measure' => ['type' => 'string'],
                ],
                'required' => ['hypothesis', 'reason', 'suggested_change', 'success_measure'],
            ],
        ];
    }

    private static function claimList(): array
    {
        return ['type' => 'array', 'items' => [
            'type' => 'object',
            'properties' => [
                'value' => ['type' => 'string'],
                'evidence_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
            ],
            'required' => ['value', 'evidence_ids', 'confidence'],
        ]];
    }

    private static function positioningProfile(): array
    {
        return [
            'name' => 'PositioningProfile',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'business_category' => ['type' => 'object', 'properties' => ['value' => ['type' => 'string'], 'evidence_ids' => ['type' => 'array', 'items' => ['type' => 'string']], 'confidence' => ['type' => 'string']], 'required' => ['value', 'evidence_ids', 'confidence']],
                    'business_model' => self::claimList(),
                    'target_audiences' => self::claimList(),
                    'core_offerings' => self::claimList(),
                    'customer_problems' => self::claimList(),
                    'customer_benefits' => self::claimList(),
                    'differentiators' => self::claimList(),
                    'geographic_scope' => self::claimList(),
                    'proof_points' => self::claimList(),
                    'brand_voice' => ['type' => 'string'],
                    'primary_conversion_goal' => ['type' => 'string', 'enum' => ['lead', 'booking', 'purchase', 'inquiry', 'awareness', 'unknown']],
                    'unknowns' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'contradictions' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['topic' => ['type' => 'string'], 'values' => ['type' => 'array', 'items' => ['type' => 'string']], 'evidence_ids' => ['type' => 'array', 'items' => ['type' => 'string']]], 'required' => ['topic', 'values', 'evidence_ids']]],
                ],
                'required' => ['business_category', 'business_model', 'target_audiences', 'core_offerings', 'customer_problems', 'customer_benefits', 'differentiators', 'geographic_scope', 'proof_points', 'brand_voice', 'primary_conversion_goal', 'unknowns', 'contradictions'],
            ],
        ];
    }

    private static function cta(): array
    {
        return ['type' => 'object', 'properties' => ['label' => ['type' => 'string'], 'destination_key' => ['type' => 'string']], 'required' => ['label', 'destination_key']];
    }

    private static function heroCopyOptions(): array
    {
        $criteria = [];
        foreach (['clarity', 'audience_relevance', 'specificity', 'customer_value', 'credibility_and_evidence', 'concision', 'brand_voice_fit'] as $c) {
            $criteria[$c] = ['type' => 'object', 'properties' => ['score' => ['type' => 'number'], 'rationale' => ['type' => 'string']], 'required' => ['score', 'rationale']];
        }

        return [
            'name' => 'HeroCopyOptions',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'positioning_summary' => ['type' => 'string'],
                    'message_hierarchy' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'relocate' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['content' => ['type' => 'string'], 'belongs_in' => ['type' => 'string']], 'required' => ['content', 'belongs_in']]],
                    'copy_options' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => [
                            'option_id' => ['type' => 'string'],
                            'strategy_id' => ['type' => 'string'],
                            'eyebrow' => ['type' => 'string'],
                            'headline' => ['type' => 'string'],
                            'description' => ['type' => 'string'],
                            'primary_cta' => self::cta(),
                            'secondary_cta' => self::cta(),
                            'trust_signals' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['text' => ['type' => 'string'], 'claim_key' => ['type' => 'string']], 'required' => ['text', 'claim_key']]],
                            'positioning_rationale' => ['type' => 'string'],
                            'evidence_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'assumptions' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'criterion_scores' => ['type' => 'object', 'properties' => $criteria, 'required' => array_keys($criteria)],
                        ],
                        'required' => ['option_id', 'strategy_id', 'headline', 'description', 'primary_cta', 'evidence_ids', 'positioning_rationale', 'criterion_scores'],
                    ]],
                    'unsupported_claim_warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'recommendation' => ['type' => 'string'],
                ],
                'required' => ['positioning_summary', 'copy_options', 'unsupported_claim_warnings', 'recommendation'],
            ],
        ];
    }

    private static function heroCopyRefine(): array
    {
        return [
            'name' => 'HeroCopyRefinement',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'eyebrow' => ['type' => 'string'],
                    'headline' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'change_summary' => ['type' => 'string'],
                ],
                'required' => ['headline', 'description', 'change_summary'],
            ],
        ];
    }
}
