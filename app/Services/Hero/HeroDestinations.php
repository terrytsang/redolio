<?php

namespace App\Services\Hero;

use App\Services\Blueprint\SectionRegistry;

/**
 * The calls to action a hero may point at are an ALLOWLIST derived from the page that will actually be built:
 * sections present in the (v1.5) DesignSpec, plus mailto:/tel: when a contact was found. The model only ever
 * chooses a key from this list; the application resolves it to a real, existing destination.
 */
class HeroDestinations
{
    private const KEY_FOR_COMPONENT = ['services' => 'section:services', 'features' => 'section:features', 'contact-form' => 'section:contact', 'gallery' => 'section:gallery',
        'pricing' => 'section:pricing', 'faq' => 'section:faq', 'product-grid' => 'section:products', 'process' => 'section:process', 'testimonials' => 'section:testimonials', 'cta' => 'section:cta'];

    public function __construct(private readonly SectionRegistry $registry) {}

    /** @return array<string, array{label: string, action_type: string, destination: string}> */
    public function forSpec(?array $designSpecJson): array
    {
        $out = [];
        foreach (($designSpecJson['v15']['pages'][0]['sections'] ?? []) as $s) {
            $key = self::KEY_FOR_COMPONENT[$s['component_id']] ?? null;
            $anchor = $this->registry->anchor($s['component_id'], $s['variant_id']);
            if ($key && $anchor && ! isset($out[$key])) {
                $out[$key] = ['label' => ucfirst(str_replace('section:', '', $key)).' section', 'action_type' => 'section_anchor', 'destination' => '#'.$anchor];
            }
        }
        foreach (($designSpecJson['v15']['pages'][0]['sections'] ?? []) as $s) {
            $action = $s['props']['form_action'] ?? null;
            if ($action && str_starts_with($action, 'mailto:')) {
                $out['mailto'] = ['label' => 'Email the business', 'action_type' => 'mailto', 'destination' => $action];
            }
            foreach ($s['props']['columns'] ?? [] as $col) {
                foreach ($col['links'] ?? [] as $l) {
                    if (str_starts_with($l['href'] ?? '', 'tel:') && ! isset($out['tel'])) {
                        $out['tel'] = ['label' => 'Call the business', 'action_type' => 'tel', 'destination' => $l['href']];
                    }
                    if (str_starts_with($l['href'] ?? '', 'mailto:') && ! isset($out['mailto'])) {
                        $out['mailto'] = ['label' => 'Email the business', 'action_type' => 'mailto', 'destination' => $l['href']];
                    }
                }
            }
        }
        if ($out === []) { // no blueprint page yet: the conventional sections the mapper will resolve later
            $out = ['section:services' => ['label' => 'Services section', 'action_type' => 'section_anchor', 'destination' => '#services'], 'section:contact' => ['label' => 'Contact section', 'action_type' => 'section_anchor', 'destination' => '#contact']];
        }

        return $out;
    }

    /** @return array{action_type: string, destination: string}|null null when the key is not an allowed destination */
    public function resolve(?string $key, array $available): ?array
    {
        return ($key !== null && isset($available[$key])) ? ['action_type' => $available[$key]['action_type'], 'destination' => $available[$key]['destination']] : null;
    }
}
