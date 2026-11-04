<?php

namespace Database\Seeders;

use App\Models\DemoFixture;
use Illuminate\Database\Seeder;

/**
 * Two full, labelled demo fixtures (FR-011) covering the entire journey
 * — snapshot, evidence, Website DNA, diagnosis, DesignSpec and evaluation
 * — using the exact post-validation shapes the live pipeline produces, so
 * they render through the same renderer with no special-casing.
 */
class DemoFixturesSeeder extends Seeder
{
    public function run(): void
    {
        DemoFixture::updateOrCreate(
            ['fixture_key' => 'brightleaf-dental'],
            [
                'display_name' => 'Brightleaf Dental Care',
                'description' => 'A local dental clinic brochure site with a thin homepage and no clear booking CTA.',
                'fixture_payload_json' => $this->brightleafDental(),
                'is_active' => true,
            ]
        );

        DemoFixture::updateOrCreate(
            ['fixture_key' => 'anchor-consulting'],
            [
                'display_name' => 'Anchor & Co. Consulting',
                'description' => 'A small consulting agency site with strong credentials but weak information architecture.',
                'fixture_payload_json' => $this->anchorConsulting(),
                'is_active' => true,
            ]
        );
    }

    private function brightleafDental(): array
    {
        $home = 'https://brightleafdental.example/';
        $services = 'https://brightleafdental.example/services';
        $contact = 'https://brightleafdental.example/contact';

        $evidenceItems = [
            ['evidence_id' => 'p0-title', 'page_url' => $home, 'evidence_type' => 'title', 'label' => 'Page title', 'excerpt' => 'Brightleaf Dental Care'],
            ['evidence_id' => 'p0-meta', 'page_url' => $home, 'evidence_type' => 'meta', 'label' => 'Meta description', 'excerpt' => 'Family and cosmetic dentistry in Brightleaf.'],
            ['evidence_id' => 'p0-h0', 'page_url' => $home, 'evidence_type' => 'heading', 'label' => 'Heading #0', 'excerpt' => 'Welcome to Brightleaf Dental Care'],
            ['evidence_id' => 'p0-para0', 'page_url' => $home, 'evidence_type' => 'paragraph', 'label' => 'Body text excerpt #0', 'excerpt' => 'We have served the Brightleaf community for over 15 years, offering gentle, modern dental care for the whole family.'],
            ['evidence_id' => 'p0-cta0', 'page_url' => $home, 'evidence_type' => 'cta', 'label' => 'CTA #0', 'excerpt' => 'Learn More'],
            ['evidence_id' => 'p0-contact0', 'page_url' => $home, 'evidence_type' => 'contact', 'label' => 'Contact detail #0', 'excerpt' => 'info@brightleafdental.example'],
            ['evidence_id' => 'p1-h0', 'page_url' => $services, 'evidence_type' => 'heading', 'label' => 'Heading #0', 'excerpt' => 'Our Services'],
            ['evidence_id' => 'p1-para0', 'page_url' => $services, 'evidence_type' => 'paragraph', 'label' => 'Body text excerpt #0', 'excerpt' => 'General checkups, teeth whitening, and emergency dental care available by appointment.'],
            ['evidence_id' => 'p1-img0', 'page_url' => $services, 'evidence_type' => 'image_alt', 'label' => 'Image alt text #0', 'excerpt' => 'Dentist examining patient'],
            ['evidence_id' => 'p2-h0', 'page_url' => $contact, 'evidence_type' => 'heading', 'label' => 'Heading #0', 'excerpt' => 'Contact Us'],
            ['evidence_id' => 'p2-form0', 'page_url' => $contact, 'evidence_type' => 'form', 'label' => 'Form #0', 'excerpt' => 'Form with fields: text, email, textarea'],
            ['evidence_id' => 'p2-contact0', 'page_url' => $contact, 'evidence_type' => 'contact', 'label' => 'Contact detail #0', 'excerpt' => '(555) 019-2834'],
            ['evidence_id' => 'p2-warn0', 'page_url' => $contact, 'evidence_type' => 'crawl_warning', 'label' => 'Crawl warning #0', 'excerpt' => 'No online booking link found on any crawled page.'],
        ];

        $snapshot = [
            'snapshot_hash' => hash('sha256', 'brightleaf-fixture'),
            'homepage_url' => $home,
            'pages' => [
                [
                    'page_url' => $home, 'status' => 'success', 'title' => 'Brightleaf Dental Care',
                    'meta_description' => 'Family and cosmetic dentistry in Brightleaf.', 'canonical_url' => $home,
                    'headings' => ['Welcome to Brightleaf Dental Care'],
                    'visible_text' => 'We have served the Brightleaf community for over 15 years, offering gentle, modern dental care for the whole family.',
                    'links' => [$services, $contact], 'ctas' => ['Learn More'], 'forms' => [],
                    'image_alt' => [], 'evidence_ids' => ['p0-title', 'p0-meta', 'p0-h0', 'p0-para0', 'p0-cta0', 'p0-contact0'],
                ],
                [
                    'page_url' => $services, 'status' => 'success', 'title' => 'Services — Brightleaf Dental Care',
                    'meta_description' => null, 'canonical_url' => $services,
                    'headings' => ['Our Services'],
                    'visible_text' => 'General checkups, teeth whitening, and emergency dental care available by appointment.',
                    'links' => [$home, $contact], 'ctas' => [], 'forms' => [],
                    'image_alt' => ['Dentist examining patient'], 'evidence_ids' => ['p1-h0', 'p1-para0', 'p1-img0'],
                ],
                [
                    'page_url' => $contact, 'status' => 'success', 'title' => 'Contact — Brightleaf Dental Care',
                    'meta_description' => null, 'canonical_url' => $contact,
                    'headings' => ['Contact Us'],
                    'visible_text' => 'Call us or send a message to schedule your visit.',
                    'links' => [$home, $services], 'ctas' => [],
                    'forms' => [['labels' => ['Name', 'Email', 'Message'], 'input_types' => ['text', 'email', 'textarea'], 'action_origin' => 'https://brightleafdental.example']],
                    'image_alt' => [], 'evidence_ids' => ['p2-h0', 'p2-form0', 'p2-contact0', 'p2-warn0'],
                ],
            ],
            'warnings' => ['No online booking link found on any crawled page.'],
            'screenshot_path' => null,
        ];

        $websiteDna = [
            'business_name' => 'Brightleaf Dental Care',
            'industry' => 'Dental / healthcare services',
            'business_summary' => 'A family and cosmetic dental practice serving the Brightleaf community for over 15 years.',
            'target_audience' => [
                ['segment' => 'Local families', 'needs' => ['routine checkups', 'trustworthy, gentle care'], 'confidence' => 'medium', 'evidence_ids' => ['p0-para0']],
            ],
            'offerings' => [
                ['name' => 'General checkups', 'description' => 'Routine dental exams and cleanings.', 'evidence_ids' => ['p1-para0']],
                ['name' => 'Teeth whitening', 'description' => 'Cosmetic whitening treatments.', 'evidence_ids' => ['p1-para0']],
                ['name' => 'Emergency care', 'description' => 'Emergency dental appointments.', 'evidence_ids' => ['p1-para0']],
            ],
            'value_proposition' => ['text' => 'Gentle, modern dental care for the whole family, from a practice the community has trusted for 15+ years.', 'evidence_ids' => ['p0-para0'], 'confidence' => 'medium'],
            'tone_of_voice' => ['warm', 'reassuring'],
            'primary_conversion_goal' => 'booking',
            'cta_inventory' => [
                ['label' => 'Learn More', 'page_url' => $home, 'evidence_ids' => ['p0-cta0']],
            ],
            'trust_signals' => [
                ['type' => 'other', 'description' => '15+ years serving the local community.', 'evidence_ids' => ['p0-para0']],
            ],
            'observed_facts' => [
                ['claim' => 'The homepage has no direct booking call-to-action, only "Learn More".', 'evidence_ids' => ['p0-cta0']],
                ['claim' => 'Contact is available via a form and phone number, not online booking.', 'evidence_ids' => ['p2-form0', 'p2-contact0']],
            ],
            'inferences' => [
                ['claim' => 'The practice likely relies on phone calls to convert most new patients.', 'confidence' => 'medium', 'evidence_ids' => ['p2-contact0', 'p2-warn0']],
            ],
            'uncertainties' => ['Pricing and insurance acceptance are not stated on any crawled page.'],
        ];

        $diagnosisReport = [
            'summary' => 'The site reads as trustworthy but makes booking an appointment harder than it needs to be, with no clear primary call-to-action.',
            'issues' => [
                [
                    'issue_id' => 'issue-1', 'title' => 'No clear booking call-to-action on the homepage', 'category' => 'cta', 'severity' => 'high',
                    'problem' => 'The homepage CTA reads "Learn More" rather than inviting visitors to book an appointment.',
                    'why_it_matters' => 'Visitors who are ready to book have no obvious next step, increasing the chance they leave without converting.',
                    'recommended_solution' => 'Replace the primary homepage CTA with a direct "Book an Appointment" action.',
                    'evidence_ids' => ['p0-cta0'], 'confidence' => 'high', 'verification_type' => 'observed',
                ],
                [
                    'issue_id' => 'issue-2', 'title' => 'Booking relies on phone or a generic contact form', 'category' => 'information_architecture', 'severity' => 'medium',
                    'problem' => 'There is no online booking link anywhere in the crawled pages; the only options are calling or a generic contact form.',
                    'why_it_matters' => 'Many patients, especially younger ones, prefer self-service booking and may avoid calling.',
                    'recommended_solution' => 'Add a dedicated booking section or link, even if it routes to a phone call or third-party scheduler for now.',
                    'evidence_ids' => ['p2-warn0', 'p2-form0'], 'confidence' => 'medium', 'verification_type' => 'observed',
                ],
                [
                    'issue_id' => 'issue-3', 'title' => 'Value proposition is understated', 'category' => 'messaging', 'severity' => 'medium',
                    'problem' => 'The 15-year track record and family-friendly positioning are only mentioned in passing on the homepage.',
                    'why_it_matters' => 'A clear, prominent value proposition builds trust faster for visitors comparing local providers.',
                    'recommended_solution' => 'Feature the practice\'s experience and family focus prominently in the hero section.',
                    'evidence_ids' => ['p0-para0'], 'confidence' => 'medium', 'verification_type' => 'model_inference',
                ],
            ],
            'overall_confidence' => 'medium',
            'limitations' => ['Only three pages were crawled; pricing and insurance information may exist elsewhere on the site.'],
        ];

        $designSpec = [
            'template_key' => 'smb_default',
            'business_name' => 'Brightleaf Dental Care',
            'primary_goal' => 'booking',
            'brand' => [
                'visual_tone' => 'warm',
                'color_tokens' => ['primary' => 'ocean', 'secondary' => 'slate', 'accent' => 'amber', 'background' => 'white', 'text' => 'ink'],
                'typography' => 'friendly_rounded',
                'spacing_scale' => 'balanced',
                'button_style' => 'rounded',
            ],
            'hero' => [
                'eyebrow' => 'Trusted by Brightleaf families for 15+ years',
                'headline' => 'Gentle, modern dental care for your whole family',
                'subheadline' => 'From routine checkups to cosmetic whitening, book your visit with a practice the community trusts.',
                'primary_cta_label' => 'Book an Appointment',
                'primary_cta_action' => 'booking',
                'secondary_cta_label' => 'View Services',
                'image_asset_key' => 'placeholder_hero',
            ],
            'sections' => [
                [
                    'section_key' => 'offerings', 'heading' => 'What we offer', 'body' => null,
                    'items' => [
                        ['title' => 'General checkups', 'description' => 'Routine dental exams and cleanings.', 'label' => null, 'asset_key' => 'placeholder_offering'],
                        ['title' => 'Teeth whitening', 'description' => 'Cosmetic whitening treatments.', 'label' => null, 'asset_key' => 'placeholder_offering'],
                        ['title' => 'Emergency care', 'description' => 'Emergency dental appointments when you need them most.', 'label' => null, 'asset_key' => 'placeholder_offering'],
                    ],
                    'source_evidence_ids' => ['p1-para0'], 'addresses_issue_ids' => ['issue-3'],
                ],
                [
                    'section_key' => 'trust', 'heading' => '15+ years caring for Brightleaf families', 'body' => 'Generations of local families trust us for gentle, modern dental care.',
                    'items' => [], 'source_evidence_ids' => ['p0-para0'], 'addresses_issue_ids' => ['issue-3'],
                ],
                [
                    'section_key' => 'contact', 'heading' => 'Ready to book?', 'body' => 'Call us or send a message — we\'ll find a time that works for you.',
                    'items' => [], 'source_evidence_ids' => ['p2-contact0', 'p2-form0'], 'addresses_issue_ids' => ['issue-1', 'issue-2'],
                ],
            ],
            'footer' => [
                'summary' => 'Brightleaf Dental Care — family and cosmetic dentistry.',
                'contact_text' => '(555) 019-2834 · info@brightleafdental.example',
                'cta_label' => 'Book an Appointment',
            ],
            'rationale' => [
                ['decision' => 'Made booking the primary hero CTA instead of a vague "Learn More".', 'addresses_issue_ids' => ['issue-1']],
                ['decision' => 'Added a dedicated contact/booking section near the end of the page.', 'addresses_issue_ids' => ['issue-2']],
                ['decision' => 'Surfaced the 15-year trust signal in its own section.', 'addresses_issue_ids' => ['issue-3']],
            ],
        ];

        $evaluationReport = [
            'items' => [
                ['issue_id' => 'issue-1', 'status' => 'addressed', 'rationale' => 'The hero CTA now directly invites booking an appointment.', 'checks' => ['Hero primary_cta_action is "booking".']],
                ['issue_id' => 'issue-2', 'status' => 'partially_addressed', 'rationale' => 'A contact/booking section was added, but it still routes to phone/form rather than true self-service scheduling.', 'checks' => ['Contact section references phone and form evidence.']],
                ['issue_id' => 'issue-3', 'status' => 'addressed', 'rationale' => 'The 15-year trust signal is now a dedicated section rather than a passing mention.', 'checks' => ['Trust section present and sourced from homepage evidence.']],
            ],
            'overall_note' => 'Coverage assessment only; not a conversion or business outcome measurement.',
        ];

        return [
            'submitted_url' => $home,
            'homepage_url' => $home,
            'snapshot' => $snapshot,
            'evidence_items' => $evidenceItems,
            'website_dna' => $websiteDna,
            'diagnosis_report' => $diagnosisReport,
            'design_spec' => $designSpec,
            'evaluation_report' => $evaluationReport,
        ];
    }

    private function anchorConsulting(): array
    {
        $home = 'https://anchorandco.example/';
        $about = 'https://anchorandco.example/about';
        $services = 'https://anchorandco.example/services';

        $evidenceItems = [
            ['evidence_id' => 'p0-title', 'page_url' => $home, 'evidence_type' => 'title', 'label' => 'Page title', 'excerpt' => 'Anchor & Co. — Strategy Consulting'],
            ['evidence_id' => 'p0-h0', 'page_url' => $home, 'evidence_type' => 'heading', 'label' => 'Heading #0', 'excerpt' => 'Strategy consulting for growing businesses'],
            ['evidence_id' => 'p0-para0', 'page_url' => $home, 'evidence_type' => 'paragraph', 'label' => 'Body text excerpt #0', 'excerpt' => 'Anchor & Co. has guided over 60 small and mid-sized businesses through growth planning since 2014.'],
            ['evidence_id' => 'p0-cta0', 'page_url' => $home, 'evidence_type' => 'cta', 'label' => 'CTA #0', 'excerpt' => 'Contact'],
            ['evidence_id' => 'p0-warn0', 'page_url' => $home, 'evidence_type' => 'crawl_warning', 'label' => 'Crawl warning #0', 'excerpt' => 'Homepage contains no summary of specific services; details are only on a separate services page.'],
            ['evidence_id' => 'p1-h0', 'page_url' => $about, 'evidence_type' => 'heading', 'label' => 'Heading #0', 'excerpt' => 'Our team'],
            ['evidence_id' => 'p1-para0', 'page_url' => $about, 'evidence_type' => 'paragraph', 'label' => 'Body text excerpt #0', 'excerpt' => 'Our founders previously led strategy teams at two Fortune 500 companies before founding Anchor & Co.'],
            ['evidence_id' => 'p2-h0', 'page_url' => $services, 'evidence_type' => 'heading', 'label' => 'Heading #0', 'excerpt' => 'Services'],
            ['evidence_id' => 'p2-para0', 'page_url' => $services, 'evidence_type' => 'paragraph', 'label' => 'Body text excerpt #0', 'excerpt' => 'Growth strategy, operational audits, and fractional executive advising.'],
            ['evidence_id' => 'p2-para1', 'page_url' => $services, 'evidence_type' => 'paragraph', 'label' => 'Body text excerpt #1', 'excerpt' => 'Engagements typically run 8 to 12 weeks with a dedicated senior consultant.'],
        ];

        $snapshot = [
            'snapshot_hash' => hash('sha256', 'anchor-fixture'),
            'homepage_url' => $home,
            'pages' => [
                [
                    'page_url' => $home, 'status' => 'success', 'title' => 'Anchor & Co. — Strategy Consulting',
                    'meta_description' => null, 'canonical_url' => $home,
                    'headings' => ['Strategy consulting for growing businesses'],
                    'visible_text' => 'Anchor & Co. has guided over 60 small and mid-sized businesses through growth planning since 2014.',
                    'links' => [$about, $services], 'ctas' => ['Contact'], 'forms' => [],
                    'image_alt' => [], 'evidence_ids' => ['p0-title', 'p0-h0', 'p0-para0', 'p0-cta0', 'p0-warn0'],
                ],
                [
                    'page_url' => $about, 'status' => 'success', 'title' => 'About — Anchor & Co.',
                    'meta_description' => null, 'canonical_url' => $about,
                    'headings' => ['Our team'],
                    'visible_text' => 'Our founders previously led strategy teams at two Fortune 500 companies before founding Anchor & Co.',
                    'links' => [$home, $services], 'ctas' => [], 'forms' => [],
                    'image_alt' => [], 'evidence_ids' => ['p1-h0', 'p1-para0'],
                ],
                [
                    'page_url' => $services, 'status' => 'success', 'title' => 'Services — Anchor & Co.',
                    'meta_description' => null, 'canonical_url' => $services,
                    'headings' => ['Services'],
                    'visible_text' => 'Growth strategy, operational audits, and fractional executive advising. Engagements typically run 8 to 12 weeks with a dedicated senior consultant.',
                    'links' => [$home, $about], 'ctas' => [], 'forms' => [],
                    'image_alt' => [], 'evidence_ids' => ['p2-h0', 'p2-para0', 'p2-para1'],
                ],
            ],
            'warnings' => ['Homepage contains no summary of specific services; details are only on a separate services page.'],
            'screenshot_path' => null,
        ];

        $websiteDna = [
            'business_name' => 'Anchor & Co.',
            'industry' => 'Management / strategy consulting',
            'business_summary' => 'A strategy consulting firm that has guided over 60 small and mid-sized businesses through growth planning since 2014.',
            'target_audience' => [
                ['segment' => 'Growing small and mid-sized businesses', 'needs' => ['growth planning', 'experienced strategic guidance'], 'confidence' => 'medium', 'evidence_ids' => ['p0-para0']],
            ],
            'offerings' => [
                ['name' => 'Growth strategy', 'description' => 'Strategic planning engagements for growing businesses.', 'evidence_ids' => ['p2-para0']],
                ['name' => 'Operational audits', 'description' => 'Review of business operations to find efficiency gains.', 'evidence_ids' => ['p2-para0']],
                ['name' => 'Fractional executive advising', 'description' => 'Part-time senior executive guidance.', 'evidence_ids' => ['p2-para0']],
            ],
            'value_proposition' => ['text' => 'Senior, Fortune-500-caliber strategy guidance for businesses that can\'t yet hire that experience full-time.', 'evidence_ids' => ['p1-para0'], 'confidence' => 'medium'],
            'tone_of_voice' => ['professional', 'confident'],
            'primary_conversion_goal' => 'inquiry',
            'cta_inventory' => [
                ['label' => 'Contact', 'page_url' => $home, 'evidence_ids' => ['p0-cta0']],
            ],
            'trust_signals' => [
                ['type' => 'credential', 'description' => 'Founders previously led strategy teams at two Fortune 500 companies.', 'evidence_ids' => ['p1-para0']],
                ['type' => 'other', 'description' => 'Over 60 businesses guided through growth planning since 2014.', 'evidence_ids' => ['p0-para0']],
            ],
            'observed_facts' => [
                ['claim' => 'The homepage does not summarize any specific service.', 'evidence_ids' => ['p0-warn0']],
                ['claim' => 'Service details and team credentials are each on separate, un-linked-from-homepage-summary pages.', 'evidence_ids' => ['p1-para0', 'p2-para0']],
            ],
            'inferences' => [
                ['claim' => 'Visitors likely need to click through two pages before understanding what Anchor & Co. actually does.', 'confidence' => 'medium', 'evidence_ids' => ['p0-warn0', 'p2-para0']],
            ],
            'uncertainties' => ['Pricing and typical engagement cost are not stated on any crawled page.'],
        ];

        $diagnosisReport = [
            'summary' => 'Strong credentials are buried on an about page while the homepage says little about what Anchor & Co. actually does.',
            'issues' => [
                [
                    'issue_id' => 'issue-1', 'title' => 'Homepage does not explain specific services', 'category' => 'information_architecture', 'severity' => 'high',
                    'problem' => 'The homepage only states a general strategy-consulting positioning with no mention of growth strategy, audits, or fractional advising.',
                    'why_it_matters' => 'Visitors may leave before reaching the services page, never learning what is actually offered.',
                    'recommended_solution' => 'Add a services summary section directly on the homepage with links to each offering.',
                    'evidence_ids' => ['p0-warn0', 'p2-para0'], 'confidence' => 'high', 'verification_type' => 'observed',
                ],
                [
                    'issue_id' => 'issue-2', 'title' => 'Strong credentials are not visible on the homepage', 'category' => 'trust', 'severity' => 'medium',
                    'problem' => 'The founders\' Fortune 500 strategy background only appears on the About page.',
                    'why_it_matters' => 'Credibility signals that could differentiate Anchor & Co. are not seen by visitors who do not click through.',
                    'recommended_solution' => 'Surface the Fortune 500 leadership credential in a trust section on the homepage.',
                    'evidence_ids' => ['p1-para0'], 'confidence' => 'medium', 'verification_type' => 'observed',
                ],
                [
                    'issue_id' => 'issue-3', 'title' => 'Generic "Contact" CTA does not reflect the conversion goal', 'category' => 'cta', 'severity' => 'medium',
                    'problem' => 'The only call-to-action is a generic "Contact" link rather than something inviting an inquiry about a specific need.',
                    'why_it_matters' => 'A specific CTA (e.g. "Book a growth strategy call") tends to convert better than a generic one.',
                    'recommended_solution' => 'Replace the generic Contact CTA with a specific inquiry-oriented call-to-action.',
                    'evidence_ids' => ['p0-cta0'], 'confidence' => 'low', 'verification_type' => 'model_inference',
                ],
            ],
            'overall_confidence' => 'medium',
            'limitations' => ['Only three pages were crawled; pricing and case studies may exist elsewhere on the site.'],
        ];

        $designSpec = [
            'template_key' => 'smb_default',
            'business_name' => 'Anchor & Co.',
            'primary_goal' => 'inquiry',
            'brand' => [
                'visual_tone' => 'professional',
                'color_tokens' => ['primary' => 'ink', 'secondary' => 'slate', 'accent' => 'ocean', 'background' => 'cream', 'text' => 'ink'],
                'typography' => 'editorial_serif',
                'spacing_scale' => 'spacious',
                'button_style' => 'solid',
            ],
            'hero' => [
                'eyebrow' => 'Strategy consulting since 2014',
                'headline' => 'Fortune 500 strategy experience, for businesses that need it most',
                'subheadline' => 'Growth strategy, operational audits, and fractional executive advising for growing businesses.',
                'primary_cta_label' => 'Book a Strategy Call',
                'primary_cta_action' => 'inquiry',
                'secondary_cta_label' => null,
                'image_asset_key' => 'placeholder_hero',
            ],
            'sections' => [
                [
                    'section_key' => 'offerings', 'heading' => 'What we do', 'body' => 'Three focused engagement types, each led by a dedicated senior consultant.',
                    'items' => [
                        ['title' => 'Growth strategy', 'description' => 'Strategic planning engagements for growing businesses.', 'label' => null, 'asset_key' => 'placeholder_offering'],
                        ['title' => 'Operational audits', 'description' => 'Review of business operations to find efficiency gains.', 'label' => null, 'asset_key' => 'placeholder_offering'],
                        ['title' => 'Fractional executive advising', 'description' => 'Part-time senior executive guidance.', 'label' => null, 'asset_key' => 'placeholder_offering'],
                    ],
                    'source_evidence_ids' => ['p2-para0'], 'addresses_issue_ids' => ['issue-1'],
                ],
                [
                    'section_key' => 'trust', 'heading' => 'Led by Fortune 500 strategy veterans', 'body' => 'Our founders led strategy teams at two Fortune 500 companies before founding Anchor & Co.',
                    'items' => [], 'source_evidence_ids' => ['p1-para0'], 'addresses_issue_ids' => ['issue-2'],
                ],
                [
                    'section_key' => 'contact', 'heading' => 'Ready to talk growth strategy?', 'body' => 'Book a call to discuss your business\'s specific growth plan.',
                    'items' => [], 'source_evidence_ids' => ['p0-cta0'], 'addresses_issue_ids' => ['issue-3'],
                ],
            ],
            'footer' => [
                'summary' => 'Anchor & Co. — strategy consulting for growing businesses since 2014.',
                'contact_text' => null,
                'cta_label' => 'Book a Strategy Call',
            ],
            'rationale' => [
                ['decision' => 'Added a homepage services summary linking to each offering.', 'addresses_issue_ids' => ['issue-1']],
                ['decision' => 'Promoted the Fortune 500 founder credential to a homepage trust section.', 'addresses_issue_ids' => ['issue-2']],
                ['decision' => 'Replaced the generic Contact CTA with a specific "Book a Strategy Call" action.', 'addresses_issue_ids' => ['issue-3']],
            ],
        ];

        $evaluationReport = [
            'items' => [
                ['issue_id' => 'issue-1', 'status' => 'addressed', 'rationale' => 'Homepage now includes a dedicated offerings section summarizing all three services.', 'checks' => ['Offerings section present with three items sourced from services page evidence.']],
                ['issue_id' => 'issue-2', 'status' => 'addressed', 'rationale' => 'The Fortune 500 founder credential now appears in a homepage trust section.', 'checks' => ['Trust section sourced from about-page evidence.']],
                ['issue_id' => 'issue-3', 'status' => 'addressed', 'rationale' => 'The hero and contact section both use a specific "Book a Strategy Call" CTA instead of a generic Contact link.', 'checks' => ['Hero primary_cta_label is specific; primary_cta_action is "inquiry".']],
            ],
            'overall_note' => 'Coverage assessment only; not a conversion or business outcome measurement.',
        ];

        return [
            'submitted_url' => $home,
            'homepage_url' => $home,
            'snapshot' => $snapshot,
            'evidence_items' => $evidenceItems,
            'website_dna' => $websiteDna,
            'diagnosis_report' => $diagnosisReport,
            'design_spec' => $designSpec,
            'evaluation_report' => $evaluationReport,
        ];
    }
}
