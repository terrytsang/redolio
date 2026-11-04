<?php

namespace App\Services\Blueprint;

use App\Exceptions\InvalidBlueprintException;
use App\Services\Catalogue\DesignCatalogue;
use App\Services\Editing\SectionOverrides;
use App\Services\Widgets\WidgetCatalog;

/**
 * Deterministically renders a blueprint + section content + design direction
 * to a standalone HTML page. The section sequence comes from the blueprint
 * definition (never from a screenshot), views come only from the registry.
 */
class BlueprintRenderer
{
    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly BlueprintValidator $validator,
        private readonly TokenCssCompiler $tokens,
        private readonly DesignCatalogue $catalogue,
        private readonly LayoutResolver $layout,
        private readonly BlueprintFonts $fonts,
        private readonly SectionOverrides $overrides,
        private readonly WidgetCatalog $widgets,
    ) {}

    /** @param array{sample_banner?: bool} $options */
    public function render(array $blueprint, array $content, string $directionId, array $options = []): string
    {
        $direction = $this->catalogue->direction($directionId);
        if (! $direction || ! app(BlueprintCatalog::class)->compatible($blueprint, $directionId)) {
            throw new InvalidBlueprintException(["direction [{$directionId}] is not compatible with blueprint [{$blueprint['id']}]"]);
        }

        $blueprint = $this->layout->resolve($blueprint, $direction);
        $errors = array_merge($this->validator->validateBlueprint($blueprint), $this->validator->validateContent($blueprint, $content));
        if ($errors) {
            throw new InvalidBlueprintException($errors);
        }

        $sections = [];
        foreach ($blueprint['section_sequence'] as $s) {
            // validated above: only optional sections can be absent
            isset($content[$s['instance_id']]) && $sections[] = $s + ['props' => $content[$s['instance_id']]];
        }

        return $this->document($sections, $direction, [], ['sample_banner' => $options['sample_banner'] ?? false, 'title' => $blueprint['name']], $blueprint['id']);
    }

    /**
     * Renders ONLY from a validated DesignSpec 1.5 block — the sections, props and
     * tokens in the spec are exactly what is compiled.
     *
     * @param  array<string, string>  $assetUrls  asset_id => URL (already resolved server-side)
     * @param  array{noindex?: bool}  $options
     */
    public function renderSpec(array $v15, array $assetUrls = [], array $options = []): string
    {
        $direction = $this->catalogue->direction($v15['design']['direction_id']);
        if (! $direction) {
            throw new InvalidBlueprintException(['unknown direction in DesignSpec']);
        }
        foreach ($v15['pages'][0]['sections'] as $s) {
            if (! $this->registry->has($s['component_id'], $s['variant_id'])) {
                throw new InvalidBlueprintException(["unregistered component {$s['component_id']}:{$s['variant_id']}"]);
            }
        }

        $page = $v15['pages'][0];
        $name = $v15['project']['business_name'];
        $seo = [
            'title' => $page['page_title'], 'description' => $page['meta_description'], 'noindex' => $options['noindex'] ?? false,
            'json_ld' => array_filter(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $name, 'description' => $page['meta_description']]),
        ];

        return $this->document($page['sections'], $direction + ['token_overrides' => $v15['design']['token_overrides'] ?: []], $assetUrls, ['title' => $page['page_title'], 'seo' => $seo, 'sample_banner' => false, 'widgets' => $page['widgets'] ?? [], 'publish' => $options['publish'] ?? false, 'alpha_assets' => $options['alpha_assets'] ?? []], $v15['design']['blueprint_id']);
    }

    /** What a published copy of this page needs besides index.html: embed documents and whether the widgets script is used. @return array{embeds: array<string, string>, script: bool} */
    public function widgetFiles(array $v15): array
    {
        $r = $this->widgets->renderAll($v15['pages'][0]['widgets'] ?? [], array_column($v15['pages'][0]['sections'], 'instance_id'), [], true);

        return ['embeds' => $r['embeds'], 'script' => $r['hasScript']];
    }

    private function document(array $sections, array $direction, array $assetUrls, array $options, string $blueprintId): string
    {
        $profile = collect($this->catalogue->tokenProfiles())->firstWhere('id', $direction['token_profile_base']);
        $overrides = $direction['token_overrides'] ?? [];
        view()->share('alphaAssets', $options['alpha_assets'] ?? []); // every section and widget view can tell a see-through picture (reset on each render)
        $widgets = $this->widgets->renderAll($options['widgets'] ?? [], array_column($sections, 'instance_id'), $assetUrls, (bool) ($options['publish'] ?? false));
        $html = $widgets['top'] !== '' ? [$widgets['top']] : [];
        foreach ($sections as $s) {
            $html[] = $this->overrides->apply(view($this->registry->view($s['component_id'], $s['variant_id']), [
                'c' => $s['props'], 'id' => $s['instance_id'], 'assetUrls' => $assetUrls, 'artStyle' => $direction['art_style'] ?? 'stripes',
            ])->render(), $s);
            isset($widgets['after'][$s['instance_id']]) && $html[] = $widgets['after'][$s['instance_id']];
        }

        return view('blueprints.page', [
            'title' => $options['title'],
            'fontCss' => $this->fonts->css(array_merge($this->tokens->fontIds($profile, $overrides), $this->overrides->fontIds($sections))),
            'tokenCss' => $this->tokens->compile($profile, $overrides),
            'sections' => $html,
            'widgetsHead' => $widgets['head'], 'widgetsEnd' => $widgets['end'], 'widgetsScript' => ($options['publish'] ?? false) && $widgets['hasScript'],
            'blueprint' => ['id' => $blueprintId, 'name' => $options['title']],
            'direction' => $direction,
            'sampleBanner' => $options['sample_banner'] ?? false,
            'seo' => $options['seo'] ?? [],
        ])->render();
    }
}
