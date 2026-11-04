<?php

namespace App\Services\Editing;

use App\Models\AnalysisRun;
use App\Services\Assets\AssetResolver;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\SectionRegistry;

/**
 * What a visitor may edit, derived from the section registry's typed props — never from the rendered HTML. The editor
 * (inline and form) is generated from this list, and the server re-checks every change against the same registry.
 */
class EditableFields
{
    private const LABELS = [
        'eyebrow' => 'Small label above the headline', 'headline' => 'Headline', 'subheadline' => 'Description', 'primary_cta' => 'Main button', 'secondary_cta' => 'Second button',
        'heading' => 'Heading', 'subheading' => 'Intro text', 'title' => 'Title', 'description' => 'Text', 'caption' => 'Caption', 'quote' => 'Quote', 'name' => 'Name', 'role' => 'Role',
        'meta' => 'Detail', 'cta' => 'Button', 'image' => 'Picture', 'brand' => 'Business name', 'tagline' => 'Tagline', 'legal' => 'Small print', 'label' => 'Label', 'body' => 'Text',
    ];

    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly BlueprintCatalog $blueprints,
        private readonly AssetResolver $assets,
        private readonly SectionOverrides $overrides,
    ) {}

    /** @return array<int, array{instance_id: string, component_id: string, variant_id: string, label: string, optional: bool, fields: array<int, array<string, mixed>>}> */
    public function forSpec(array $v15, AnalysisRun $run): array
    {
        $bp = $this->blueprints->find($v15['design']['blueprint_id'] ?? '');
        $out = [];
        foreach ($v15['pages'][0]['sections'] as $s) {
            $def = $bp ? collect($bp['section_sequence'])->firstWhere('instance_id', $s['instance_id']) : null;
            $out[] = [
                'instance_id' => $s['instance_id'], 'component_id' => $s['component_id'], 'variant_id' => $s['variant_id'],
                'label' => $this->sectionLabel($s['component_id']), 'optional' => (bool) ($def['optional'] ?? false) && ! in_array($s['component_id'], ['hero', 'navigation', 'footer'], true),
                'fields' => $this->walk($this->registry->props($s['component_id'], $s['variant_id']), $s['props'], '', $run, $s['component_id'] === 'hero', '', $s['overrides']['fields'] ?? []),
                'layout' => ['width' => $s['overrides']['layout']['width'] ?? 'standard', 'split' => $s['overrides']['layout']['split'] ?? null, 'supports_split' => $this->overrides->supportsSplit($s['component_id'], $s['variant_id'])],
            ];
        }

        return $out;
    }

    /** Hidden sections the user can bring back. @return array<int, array{instance_id: string, label: string}> */
    public function hidden(array $v16): array
    {
        return array_values(array_map(fn ($h) => ['instance_id' => $h['section']['instance_id'], 'label' => $this->sectionLabel($h['section']['component_id'])], $v16['hidden_sections'] ?? []));
    }

    private function walk(array $schema, array $props, string $prefix, AnalysisRun $run, bool $isHero, string $itemLabel = '', array $styles = []): array
    {
        $fields = [];
        foreach ($schema as $key => $rule) {
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";
            $value = $props[$key] ?? null;
            $label = ($itemLabel !== '' ? $itemLabel.' — ' : '').(self::LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key)));
            switch ($rule['type']) {
                case 'string':
                case 'text':
                    $fields[] = ['path' => $path, 'type' => $rule['type'], 'label' => $label, 'value' => (string) ($value ?? ''), 'max' => (int) ($rule['max'] ?? 800), 'required' => (bool) ($rule['required'] ?? false), 'style' => $this->style($styles, $path), 'linkable' => true];
                    break;
                case 'cta':
                    $fields[] = ['path' => $path, 'type' => 'cta', 'label' => $label, 'value' => $value['label'] ?? '', 'href' => $value['href'] ?? '', 'required' => (bool) ($rule['required'] ?? false), 'max' => 50, 'style' => $this->style($styles, $path), 'linkable' => false];
                    break;
                case 'image':
                    $id = $value['asset_id'] ?? null;
                    $fields[] = ['path' => $path, 'type' => 'image', 'label' => $label, 'asset_id' => $id, 'alt' => $value['alt'] ?? '', 'url' => $id ? $this->assets->byAssetKey($run, $id)?->url() : null, 'required' => false, 'hero' => $isHero, 'style' => $this->style($styles, $path), 'linkable' => (bool) $id];
                    break;
                case 'list':
                    foreach (array_values((array) $value) as $i => $item) {
                        if (is_array($item) && is_array($rule['items'] ?? null)) {
                            array_push($fields, ...$this->walk($rule['items'], $item, "{$path}.{$i}", $run, false, ucfirst(rtrim(self::LABELS[$key] ?? $key, 's')).' '.($i + 1), $styles));
                        }
                    }
                    break;
                default: // href, icon, bool, enum, strlist: structural, not free text
            }
        }

        return $fields;
    }

    /** @return array{size: ?int, font: ?string, href: string} */
    private function style(array $styles, string $path): array
    {
        $o = $styles[$path] ?? [];

        return ['size' => $o['size'] ?? null, 'font' => $o['font'] ?? null, 'href' => $o['href'] ?? ''];
    }

    private function sectionLabel(string $componentId): string
    {
        return match ($componentId) {
            'cta' => 'Call to action', 'faq' => 'FAQ', 'trust-strip' => 'Trust strip', 'product-grid' => 'Products', 'contact-form' => 'Contact', 'navigation' => 'Navigation', default => ucfirst(str_replace('-', ' ', $componentId)),
        };
    }
}
