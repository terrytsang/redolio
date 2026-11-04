<?php

namespace App\Services\Blueprint;

use App\Models\AnalysisRun;
use App\Models\DesignSelection;
use App\Models\DesignSpec;
use App\Services\Catalogue\DesignCatalogue;
use App\Services\Editing\SpecEditException;
use App\Services\Widgets\WidgetCatalog;
use Illuminate\Support\Facades\File;

/**
 * Builds and validates the DesignSpec 1.5 "blueprint block" that sits beside the
 * legacy fields in design_spec_json['v15']. It is the contract between content
 * mapping and the trusted renderer: the renderer reads ONLY this block, so what
 * was approved is exactly what is compiled (deterministic, auditable).
 */
class DesignSpecV15
{
    public function __construct(
        private readonly BlueprintCatalog $blueprints,
        private readonly SectionRegistry $registry,
        private readonly ContentMappingService $mapper,
        private readonly ContentValidator $content,
        private readonly JsonSchemaSubset $schema,
        private readonly TokenCssCompiler $tokens,
        private readonly DesignCatalogue $catalogue,
        private readonly LayoutResolver $layout,
        private readonly WidgetCatalog $widgets,
    ) {}

    /**
     * Builds, validates and (only when valid) stores the v15 block on the spec.
     *
     * @return array{attached: bool, errors: string[], partial: bool}
     */
    public function attach(DesignSpec $spec, DesignSelection $selection, ?string $blueprintId = null): array
    {
        $run = $spec->analysisRun;
        $blueprint = $this->blueprints->find($blueprintId ?? (string) $selection->blueprint_id);
        $dna = $run->currentWebsiteDna()?->dna_json ?? [];

        if (! $blueprint) {
            return ['attached' => false, 'errors' => ['Blueprint not found.'], 'partial' => false];
        }

        $json = $spec->design_spec_json;
        unset($json['v15'], $json['v15_errors']);

        $v15 = $this->build($run, $json, $dna, $blueprint, $selection);
        $result = $this->validate($v15, $run);
        $v15['validation'] = $result['flags'];

        if ($result['errors']) {
            $json['v15_errors'] = $result['errors'];
            $spec->update(['design_spec_json' => $json]);

            return ['attached' => false, 'errors' => $result['errors'], 'partial' => false];
        }

        $json['v15'] = $v15;
        $spec->update(['design_spec_json' => $json]);

        return ['attached' => true, 'errors' => [], 'partial' => $v15['content_mapping']['status'] === 'partial'];
    }

    public function build(AnalysisRun $run, array $spec, array $dna, array $blueprint, DesignSelection $selection): array
    {
        $direction = $this->catalogue->direction($selection->direction_key) ?? [];
        $blueprint = $this->layout->resolve($blueprint, $direction); // the direction's layout personality, within the blueprint's limits
        $blueprint = HeroLayoutChoices::apply($blueprint, $selection->preferences_json ?? []); // the person's own hero choice wins
        $map = $this->mapper->map($run, $blueprint, $spec, $dna);
        $prefs = $selection->preferences_json ?? [];

        $sections = [];
        foreach ($blueprint['section_sequence'] as $s) {
            if (! isset($map['content'][$s['instance_id']])) {
                continue;
            }
            $sections[] = [
                'instance_id' => $s['instance_id'], 'component_id' => $s['component_id'], 'variant_id' => $s['variant_id'],
                'content_bindings' => $map['bindings'][$s['instance_id']] ?? [],
                'asset_bindings' => $map['asset_bindings'][$s['instance_id']] ?? [],
                'props' => $map['content'][$s['instance_id']],
            ];
        }

        $evidence = [];
        foreach ($spec['sections'] ?? [] as $sec) {
            array_push($evidence, ...($sec['source_evidence_ids'] ?? []));
        }
        $form = $this->formFor($map['content']);
        $businessName = $spec['business_name'] ?? '';

        return [
            'schema_version' => '1.5',
            'project' => [
                'project_id' => $run->public_id, 'source_url' => $run->normalized_url, 'business_name' => $businessName,
                'business_category' => $dna['industry'] ?? null, 'primary_goal' => $spec['primary_goal'] ?? 'inquiry',
            ],
            'brand' => [
                'brand_summary' => isset($dna['business_summary']) ? mb_substr($dna['business_summary'], 0, 600) : null,
                'brand_voice' => array_values(array_map(fn ($v) => mb_substr($v, 0, 60), (array) ($dna['tone_of_voice'] ?? []))),
                'logo_asset_id' => $run->websiteAssets()->where('asset_type', 'logo')->value('asset_key'),
                'brand_colours' => $spec['brand']['color_tokens'] ?? [],
                'brand_continuity_policy' => match (true) {
                    ! empty($prefs['preserve_logo']) && ! empty($prefs['preserve_wording']) => 'preserve-logo-and-wording',
                    ! empty($prefs['preserve_logo']) => 'preserve-logo',
                    ! empty($prefs['preserve_wording']) => 'preserve-wording',
                    default => 'free',
                },
            ],
            'design' => [
                'category_id' => $selection->category_id, 'direction_id' => $selection->direction_key,
                'direction_version' => (int) $selection->direction_version, 'blueprint_id' => $blueprint['id'],
                'blueprint_version' => (int) $blueprint['version'], 'token_profile_id' => $direction['token_profile'] ?? '',
                'token_overrides' => $direction['token_overrides'] ?? [], 'catalogue_version' => $this->catalogue->version(),
            ],
            'pages' => [[
                'route' => '/', 'page_title' => $map['page']['title'] ?: $businessName,
                'meta_description' => $map['page']['description'], 'sections' => $sections,
            ]],
            'content_mapping' => [
                'status' => $map['required_complete'] ? 'complete' : 'partial',
                'required_slots_complete' => $map['required_complete'], 'omitted_sections' => $map['omitted'],
            ],
            'responsive' => [
                'breakpoint_profile' => $blueprint['responsive_rules']['breakpoint_profile'],
                'mobile_navigation' => $blueprint['responsive_rules']['mobile_navigation'],
                'section_order_overrides' => $blueprint['responsive_rules']['section_order_overrides'],
                'visibility_rules' => $blueprint['responsive_rules']['visibility_rules'],
            ],
            'seo' => [
                'title_template' => '%s',
                'canonical_policy' => 'self',
                'open_graph' => ['type' => 'website', 'title' => $map['page']['title'], 'description' => $map['page']['description']],
                'structured_data_policy' => 'organization-verified-facts-only',
            ],
            'forms' => $form ? [$form] : [],
            'provenance' => [
                'content_evidence_ids' => array_values(array_unique($evidence)),
                'asset_source_ids' => $map['asset_ids'],
                'model_run_ids' => $run->modelCallLogs()->whereIn('stage', ['website_understanding', 'diagnosis', 'redesign_recommendations'])->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ],
            'validation' => ['schema_valid' => false, 'registry_valid' => false, 'content_integrity_valid' => false, 'asset_integrity_valid' => false],
        ];
    }

    /**
     * @return array{errors: string[], flags: array<string, bool>}
     */
    public function validate(array $v15, AnalysisRun $run): array
    {
        $schemaErrors = $this->schema->validate($this->schemaDoc(), $v15);
        $registryErrors = $contentErrors = $assetErrors = [];

        $design = $v15['design'] ?? [];
        $blueprint = $this->blueprints->find($design['blueprint_id'] ?? '');
        $direction = $this->catalogue->direction($design['direction_id'] ?? '');
        $blueprint || $registryErrors[] = 'blueprint is not in the catalogue';
        if ($blueprint && ($design['blueprint_version'] ?? null) !== $blueprint['version']) {
            $registryErrors[] = 'blueprint version is stale';
        }
        if ($blueprint && ! $this->blueprints->compatible($blueprint, $design['direction_id'] ?? '')) {
            $registryErrors[] = 'direction is not compatible with this blueprint';
        }
        $direction || $registryErrors[] = 'direction is not in the catalogue';
        if (($design['category_id'] ?? null) !== null && ! in_array($design['category_id'], array_column($this->catalogue->categories(), 'id'), true)) {
            $registryErrors[] = 'unknown category';
        }
        if ($direction) {
            $profile = collect($this->catalogue->tokenProfiles())->firstWhere('id', $direction['token_profile_base']);
            try {
                $this->tokens->compile($profile, (array) ($design['token_overrides'] ?? []));
            } catch (\InvalidArgumentException $e) {
                $registryErrors[] = $e->getMessage();
            }
        }

        $routes = array_column($v15['pages'] ?? [], 'route');
        count($routes) === count(array_unique($routes)) || $registryErrors[] = 'duplicate page routes';

        $sections = $v15['pages'][0]['sections'] ?? [];
        $ids = array_column($sections, 'instance_id');
        count($ids) === count(array_unique($ids)) || $registryErrors[] = 'duplicate section instance ids';

        $anchors = ['top' => true];
        $assetKeys = array_flip($run->websiteAssets()->pluck('asset_key')->all());
        $hasMailtoForm = false;
        foreach ($sections as $sec) {
            $cid = $sec['component_id'] ?? '';
            $vid = $sec['variant_id'] ?? '';
            if (! $this->registry->has($cid, $vid)) {
                $registryErrors[] = 'unregistered component ['.($sec['instance_id'] ?? '?').": {$cid}:{$vid}]";

                continue;
            }
            $def = $blueprint ? collect($blueprint['section_sequence'])->firstWhere('instance_id', $sec['instance_id']) : null;
            if ($blueprint && (! $def || $def['component_id'] !== $cid)) {
                $registryErrors[] = "section [{$sec['instance_id']}] is not part of the selected blueprint";
            } elseif ($def && ! empty($def['variant_locked']) && $def['variant_id'] !== $vid) {
                $registryErrors[] = "section [{$sec['instance_id']}] has a locked variant";
            }
            array_push($contentErrors, ...$this->content->validate($this->registry->props($cid, $vid), $sec['props'] ?? [], $sec['instance_id']));
            ($a = $this->registry->anchor($cid, $vid)) && $anchors[$a] = true;
            $hasMailtoForm = $hasMailtoForm || ! empty($sec['props']['form_action']);

            array_walk_recursive($sec['props'], function ($v, $k) use (&$assetErrors, $assetKeys, $sec) {
                if ($k === 'asset_id' && ! isset($assetKeys[$v])) {
                    $assetErrors[] = "{$sec['instance_id']}: asset [{$v}] does not belong to this project";
                }
            });
        }

        // Internal navigation targets must resolve to a rendered section.
        foreach ($sections as $sec) {
            array_walk_recursive($sec['props'], function ($v, $k) use (&$contentErrors, $anchors, $sec) {
                if ($k === 'href' && is_string($v) && str_starts_with($v, '#') && ! isset($anchors[substr($v, 1)])) {
                    $contentErrors[] = "{$sec['instance_id']}: link target {$v} does not exist on the page";
                }
            });
        }
        if ($hasMailtoForm && empty($v15['forms'])) {
            $contentErrors[] = 'a form submits to a mailto address but no form adapter is declared';
        }
        foreach (($v15['pages'][0]['widgets'] ?? []) as $w) {
            $wid = is_array($w) ? (string) ($w['id'] ?? '') : '';
            if (! preg_match('/^w[a-z0-9]{4,12}$/', $wid) || ! $this->widgets->has((string) ($w['type'] ?? ''))) {
                $registryErrors[] = 'unknown add-on';

                continue;
            }
            try {
                $this->widgets->clean($w['type'], (array) ($w['config'] ?? []), $run);
            } catch (SpecEditException $e) {
                $contentErrors[] = "add-on {$wid}: ".$e->getMessage();
            }
        }
        $logo = $v15['brand']['logo_asset_id'] ?? null;
        $logo && ! isset($assetKeys[$logo]) && $assetErrors[] = 'logo asset does not belong to this project';

        $flags = [
            'schema_valid' => $schemaErrors === [], 'registry_valid' => $registryErrors === [],
            'content_integrity_valid' => $contentErrors === [], 'asset_integrity_valid' => $assetErrors === [],
        ];

        return ['errors' => array_merge($schemaErrors, $registryErrors, $contentErrors, $assetErrors), 'flags' => $flags];
    }

    private function formFor(array $content): ?array
    {
        foreach ($content as $instanceId => $props) {
            if (! empty($props['form_action']) && isset($props['fields'])) {
                return [
                    'form_id' => $instanceId, 'fields' => array_column($props['fields'], 'name'),
                    'validation_schema_id' => 'contact-form-v1', 'submission_adapter' => 'mailto',
                ];
            }
        }

        return null;
    }

    private function schemaDoc(): array
    {
        return json_decode(File::get(database_path('catalogue/schemas/design-spec-1.5.schema.json')), true);
    }
}
