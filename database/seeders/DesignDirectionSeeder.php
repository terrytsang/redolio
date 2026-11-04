<?php

namespace Database\Seeders;

use App\Models\DesignDirection;
use Illuminate\Database\Seeder;

/**
 * Seeds the versioned design-direction catalogue (PRD upgrade v1.2
 * design_intelligence_engine). Only editorial/minimal/modern_corporate/
 * creative_studio/premium_luxury/commerce_energetic are is_selectable=true
 * — they have real, implemented Blade templates
 * (resources/views/components/preview/*.blade.php). The rest are
 * metadata-only stubs: real catalogue entries for future expansion, but
 * never offered to a user since rendering them would fail (AT-32).
 */
class DesignDirectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->selectableDirections() as $key => $recipe) {
            DesignDirection::updateOrCreate(
                ['direction_key' => $key],
                [
                    'name' => $recipe['name'],
                    'version' => 1,
                    'recipe_json' => $recipe,
                    'is_selectable' => true,
                    'created_at' => now(),
                ]
            );
        }

        foreach ($this->stubDirections() as $key => $recipe) {
            DesignDirection::updateOrCreate(
                ['direction_key' => $key],
                [
                    'name' => $recipe['name'],
                    'version' => 1,
                    'recipe_json' => $recipe,
                    'is_selectable' => false,
                    'created_at' => now(),
                ]
            );
        }
    }

    private function selectableDirections(): array
    {
        return [
            'editorial' => [
                'direction_id' => 'editorial',
                'name' => 'Premium Editorial',
                'family' => 'editorial',
                'description' => 'Expressive display typography, warm neutral surfaces, and an asymmetric two-column hero built for a named person or point of view.',
                'best_for' => ['personal brands', 'consultants', 'thought leaders', 'professional portfolios'],
                'brand_traits' => ['expressive typography', 'warm neutral surfaces', 'editorial grids', 'asymmetric composition'],
                'color_system' => ['background' => '#FAF6EF', 'surface' => '#F1EADC', 'text' => '#221E1A', 'text_muted' => '#6B6358', 'accent' => '#6D3B2E', 'accent_contrast' => '#FAF6EF'],
                'typography_system' => ['display' => 'font-serif', 'body' => 'font-sans'],
                'layout_compositions' => ['asymmetric_two_column', 'full_width_editorial'],
                'hero_patterns' => ['editorial_split_hero'],
                'section_patterns' => ['intro_text_section', 'portfolio_grid', 'case_study_section', 'testimonial_section'],
                'image_treatment' => 'Portrait or hero image cropped to a tall rectangle, placed opposite the headline column; never full-bleed background.',
                'spacing_system' => 'spacious',
                'component_theme' => 'editorial_nav, editorial_footer',
                'responsive_rules' => 'Two-column hero stacks to single column under 768px; image moves above headline.',
                'accessibility_rules' => 'Body text minimum 16px, text/background contrast ratio >= 4.5:1.',
                'preview_thumbnail' => null,
                'version' => 1,
            ],
            'minimal' => [
                'direction_id' => 'minimal',
                'name' => 'Modern Minimal',
                'family' => 'minimal',
                'description' => 'Restrained neutral palette, generous whitespace, and a centered hero built around one clear message and one clear action.',
                'best_for' => ['consultants', 'studios', 'small businesses', 'portfolio sites'],
                'brand_traits' => ['restrained palette', 'generous whitespace', 'clear typography', 'simple grid'],
                'color_system' => ['background' => '#FFFFFF', 'surface' => '#F7F7F5', 'text' => '#18181B', 'text_muted' => '#71717A', 'accent' => '#18181B', 'accent_contrast' => '#FFFFFF'],
                'typography_system' => ['display' => 'font-sans', 'body' => 'font-sans'],
                'layout_compositions' => ['minimal_centered', 'stacked_mobile_first'],
                'hero_patterns' => ['minimal_centered_hero'],
                'section_patterns' => ['service_card_grid', 'feature_grid', 'testimonial_section'],
                'image_treatment' => 'Small, restrained imagery only where it clarifies content; no decorative imagery.',
                'spacing_system' => 'spacious',
                'component_theme' => 'minimal_nav, minimal_footer',
                'responsive_rules' => 'Single centered column at all widths; grid sections collapse to 1 column under 640px.',
                'accessibility_rules' => 'Body text minimum 16px, text/background contrast ratio >= 4.5:1.',
                'preview_thumbnail' => null,
                'version' => 1,
            ],
            'modern_corporate' => [
                'direction_id' => 'modern_corporate',
                'name' => 'Modern Corporate',
                'family' => 'corporate',
                'description' => 'Structured hierarchy, trust-led content and a balanced, image-led hero built for B2B and professional-services credibility.',
                'best_for' => ['B2B services', 'professional firms', 'corporate websites'],
                'brand_traits' => ['structured hierarchy', 'trust-led content', 'balanced layouts', 'restrained colour'],
                'color_system' => ['background' => '#FFFFFF', 'surface' => '#F1F5F9', 'text' => '#0F172A', 'text_muted' => '#475569', 'accent' => '#1D4ED8', 'accent_contrast' => '#FFFFFF'],
                'typography_system' => ['display' => 'font-sans', 'body' => 'font-sans'],
                'layout_compositions' => ['image_led', 'editorial_split'],
                'hero_patterns' => ['asymmetric_split_hero', 'image_led_hero'],
                'section_patterns' => ['service_card_grid', 'case_study_section', 'testimonial_section'],
                'image_treatment' => 'Rectangular, evenly cropped imagery with consistent aspect ratio across cards.',
                'spacing_system' => 'balanced',
                'component_theme' => 'corporate_nav, corporate_footer',
                'responsive_rules' => 'Hero image stacks below headline under 768px; card grids collapse to 1 column under 640px.',
                'accessibility_rules' => 'Body text minimum 16px, text/background contrast ratio >= 4.5:1.',
                'preview_thumbnail' => null,
                'version' => 1,
            ],
            'creative_studio' => [
                'direction_id' => 'creative_studio',
                'name' => 'Creative Studio',
                'family' => 'creative',
                'description' => 'Bold oversized typography, a near-black canvas, and a single vivid accent — built for brands that want to feel confident and visually distinctive, not safe.',
                'best_for' => ['creative agencies', 'designers', 'production studios', 'personal brands who want bold'],
                'brand_traits' => ['bold composition', 'oversized typography', 'vivid accent color', 'project-led grid'],
                'color_system' => ['background' => '#0E0E10', 'surface' => '#19191C', 'text' => '#F5F5F0', 'text_muted' => '#A3A3A3', 'accent' => '#FF5B2E', 'accent_contrast' => '#0E0E10'],
                'typography_system' => ['display' => 'font-sans', 'body' => 'font-sans'],
                'layout_compositions' => ['full_width_editorial', 'minimal_centered'],
                'hero_patterns' => ['editorial_split_hero'],
                'section_patterns' => ['portfolio_grid', 'case_study_section', 'service_card_grid'],
                'image_treatment' => 'Full-bleed wide hero imagery; project thumbnails cropped to consistent 4:3 cards with no borders, just dark surface padding.',
                'spacing_system' => 'balanced',
                'component_theme' => 'dark_nav, dark_footer',
                'responsive_rules' => 'Hero headline scales down aggressively under 768px to stay legible; grids collapse to 1 column under 640px.',
                'accessibility_rules' => 'Body text minimum 16px, text/background contrast ratio >= 4.5:1 even on dark surfaces.',
                'preview_thumbnail' => null,
                'version' => 1,
            ],
            'premium_luxury' => [
                'direction_id' => 'premium_luxury',
                'name' => 'Premium Luxury',
                'family' => 'luxury',
                'description' => 'A near-black canvas, thin gold accents, and a centered serif hero built for brands that need to feel exclusive, considered, and unhurried.',
                'best_for' => ['premium services', 'boutique brands', 'high-end hospitality', 'luxury consultants'],
                'brand_traits' => ['refined serif typography', 'dark restrained palette', 'gold accent', 'generous whitespace'],
                'color_system' => ['background' => '#15130F', 'surface' => '#1E1B15', 'text' => '#EFE9DC', 'text_muted' => '#B6AE9C', 'accent' => '#B89A5C', 'accent_contrast' => '#15130F'],
                'typography_system' => ['display' => 'font-serif', 'body' => 'font-sans'],
                'layout_compositions' => ['minimal_centered'],
                'hero_patterns' => ['minimal_centered_hero'],
                'section_patterns' => ['intro_text_section', 'testimonial_section', 'portfolio_grid'],
                'image_treatment' => 'Single large atmospheric image beneath the hero; portrait-cropped gallery images with no visible card chrome.',
                'spacing_system' => 'spacious',
                'component_theme' => 'luxury_nav, luxury_footer',
                'responsive_rules' => 'Single centered column at all widths; gallery collapses to 1 column under 640px.',
                'accessibility_rules' => 'Body text minimum 16px, text/background contrast ratio >= 4.5:1 even on dark surfaces.',
                'preview_thumbnail' => null,
                'version' => 1,
            ],
            'commerce_energetic' => [
                'direction_id' => 'commerce_energetic',
                'name' => 'Commerce Energetic',
                'family' => 'commerce',
                'description' => 'Alternating flat-color sections and concrete, numbers-backed proof — built for approachable local businesses and service providers who want energy without losing restraint.',
                'best_for' => ['local businesses', 'service providers', 'small retail', 'approachable commerce'],
                'brand_traits' => ['alternating color rhythm', 'concrete proof', 'friendly bold type', 'high-contrast CTA'],
                'color_system' => ['background' => '#FAFAF7', 'surface' => '#0F4C3A', 'text' => '#14110F', 'text_muted' => '#6B6358', 'accent' => '#FF6B35', 'accent_contrast' => '#FFFFFF'],
                'typography_system' => ['display' => 'font-sans', 'body' => 'font-sans'],
                'layout_compositions' => ['stacked_mobile_first', 'bento_grid'],
                'hero_patterns' => ['minimal_centered_hero'],
                'section_patterns' => ['service_card_grid', 'stats_proof_section', 'testimonial_section'],
                'image_treatment' => 'One real photo per section where available; otherwise a flat-color field carries the section — never a generic icon standing in for a photo.',
                'spacing_system' => 'balanced',
                'component_theme' => 'energetic_nav, energetic_footer',
                'responsive_rules' => 'Sections stack single-column under 768px; alternating color blocks remain full-bleed at every width.',
                'accessibility_rules' => 'Body text minimum 16px, text/background contrast ratio >= 4.5:1 including on the bold color sections.',
                'preview_thumbnail' => null,
                'version' => 1,
            ],
        ];
    }

    private function stubDirections(): array
    {
        $stubs = [
            'technology' => ['name' => 'Modern Technology', 'family' => 'technology', 'best_for' => ['technology consultancies', 'IT services', 'technology products'], 'brand_traits' => ['confident typography', 'technical visual language', 'structured sections', 'dark or light variants']],
            'product_saas' => ['name' => 'Product-led SaaS', 'family' => 'saas', 'best_for' => ['SaaS', 'software products', 'digital platforms'], 'brand_traits' => ['product screenshots', 'feature hierarchy', 'proof points', 'conversion-focused CTA']],
            'professional_services' => ['name' => 'Professional Services', 'family' => 'professional', 'best_for' => ['legal', 'accounting', 'consulting', 'health and professional practices'], 'brand_traits' => ['trust signals', 'clear service structure', 'credible imagery', 'accessible navigation']],
            'hospitality' => ['name' => 'Image-led Hospitality', 'family' => 'hospitality', 'best_for' => ['restaurants', 'hotels', 'travel', 'venues'], 'brand_traits' => ['large photography', 'atmospheric imagery', 'booking CTA', 'experience-led sections']],
            'ecommerce' => ['name' => 'E-commerce', 'family' => 'ecommerce', 'best_for' => ['online stores', 'product catalogues', 'retail brands'], 'brand_traits' => ['product-first hierarchy', 'category navigation', 'shopping CTA', 'clear product cards']],
            'portfolio' => ['name' => 'Portfolio Showcase', 'family' => 'portfolio', 'best_for' => ['architects', 'photographers', 'designers', 'creative professionals'], 'brand_traits' => ['project galleries', 'case studies', 'visual proof', 'editorial project pages']],
            'bold_experimental' => ['name' => 'Bold Experimental', 'family' => 'experimental', 'best_for' => ['culture brands', 'creative projects', 'distinctive challenger brands'], 'brand_traits' => ['unconventional layouts', 'expressive typography', 'strong visual identity', 'careful usability constraints']],
        ];

        $out = [];
        foreach ($stubs as $key => $s) {
            $out[$key] = array_merge($s, [
                'direction_id' => $key,
                'description' => $s['name'].' — catalogued for future expansion; not yet implemented as a renderable recipe.',
                'version' => 1,
            ]);
        }

        return $out;
    }
}
