<?php

namespace App\Services\Editing;

use App\Models\DesignSpec;
use App\Models\HeroCopyApproval;
use App\Models\User;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Blueprint\DesignSpecV15;
use App\Services\Blueprint\DesignSpecVersioner;
use App\Services\Blueprint\SectionRegistry;
use App\Services\Hero\HeroCopyVersionService;
use App\Services\Hero\HeroDestinations;
use App\Services\VisualQa\BlueprintRefiner;
use App\Services\Widgets\WidgetCatalog;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * The single, safe path for changing a generated site by hand (in-place editing, the hero review page, restore).
 *
 * Every change is expressed as an operation on a registered section prop, applied to a COPY of the spec, validated
 * against the full v1.5 contract, and stored as a new DesignSpec version — so nothing the user types can be markup,
 * an unsafe link, or an asset from another project, and the previous version is always still there to restore.
 *
 * Operations (all take instance_id):
 *   set          path, value                     text / textarea / cta label ("primary_cta.label")
 *   set_cta      path, label, [destination_key | href]   a call to action; destination_key is one of the page's real destinations
 *   set_image    path, asset_id, alt             an image from THIS project's own assets
 *   clear_image  path                            optional images only
 *   hide_section / show_section                  optional sections only
 *   revert       [path]                          back to the generated wording (a field, or the whole section)
 *   set_style    path, [size], [font]            text size (px) and typeface of a text or button
 *   set_link     path, href                      a link on text or a picture ('' removes it); buttons use set_cta
 *   add_widget / update_widget / move_widget / remove_widget   add-on widgets (see WidgetCatalog); instance_id is the section a new widget follows
 *   set_layout   [width], [split]                container width (narrow|standard|wide|full) and, for two-column rows, the first column's share (30–70)
 */
class SpecEditService
{
    /** An editing session reuses its own unreviewed draft version instead of creating one per keystroke. */
    private const COALESCE_MINUTES = 15;

    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly DesignSpecV15 $v15,
        private readonly DesignSpecVersioner $versioner,
        private readonly HeroDestinations $destinations,
        private readonly BlueprintCatalog $blueprints,
        private readonly HeroCopyVersionService $heroVersions,
        private readonly BlueprintRefiner $refiner,
        private readonly SectionOverrides $overrides,
        private readonly WidgetCatalog $widgets,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $ops
     * @return array{spec: DesignSpec, changed: int, warnings: array<int, array{instance_id: string, path: string, level: string, message: string}>, coalesced: bool}
     */
    public function apply(DesignSpec $base, int $baseVersion, array $ops, ?User $user): array
    {
        $run = $base->analysisRun;
        $latest = $run->currentDesignSpec();
        if (! $latest || $latest->id !== $base->id || $baseVersion !== $base->version) {
            throw new SpecEditException('This page was changed somewhere else. Reload to keep working on the latest version.', [], true);
        }
        $json = $base->design_spec_json;
        if (! isset($json['v15']['pages'][0]['sections'])) {
            throw new SpecEditException('This design cannot be edited in place.');
        }
        if ($ops === [] || count($ops) > 40) {
            throw new SpecEditException('Nothing to change.');
        }

        $destinations = $this->destinations->forSpec($json);
        $changed = [];
        $warnings = [];
        $v16 = $json['v16'] ?? ['schema_version' => '1.6'];
        $sections = &$json['v15']['pages'][0]['sections'];

        $widgets = $json['v15']['pages'][0]['widgets'] ?? [];
        foreach ($ops as $i => $op) {
            $kind = (string) ($op['op'] ?? '');
            $id = (string) ($op['instance_id'] ?? '');
            if (str_ends_with($kind, '_widget')) {
                $this->widgetOp($kind, $op, $widgets, $sections, $run, $changed);

                continue;
            }
            $idx = $this->indexOf($sections, $id);
            if ($kind !== 'show_section' && $idx === null) {
                throw new SpecEditException('That section is no longer on the page.', ["op {$i}: unknown section {$id}"]);
            }

            switch ($kind) {
                case 'set':
                    $this->setText($sections[$idx], $op, $v16, $warnings);
                    break;
                case 'set_cta':
                    $this->setCta($sections[$idx], $op, $destinations, $v16);
                    break;
                case 'set_image':
                    $this->setImage($sections[$idx], $op, $run, $v16);
                    break;
                case 'clear_image':
                    $this->clearImage($sections[$idx], $op, $v16);
                    break;
                case 'hide_section':
                    $this->hide($sections, $idx, $json, $v16);
                    break;
                case 'show_section':
                    $this->show($sections, $id, $v16);
                    break;
                case 'revert':
                    $this->revert($sections[$idx], $op, $v16);
                    break;
                case 'set_style':
                    $this->setStyle($sections[$idx], $op);
                    break;
                case 'set_link':
                    $this->setLink($sections[$idx], $op);
                    break;
                case 'set_layout':
                    $this->setLayout($sections[$idx], $op);
                    break;
                default:
                    throw new SpecEditException('Unsupported edit.', ["op {$i}: unknown operation"]);
            }
            $changed[] = ['instance_id' => $id, 'path' => (string) ($op['path'] ?? ($kind === 'revert' ? '*' : '#'.$kind)), 'kind' => $kind];
        }
        unset($sections);
        $widgets === [] ? Arr::forget($json, 'v15.pages.0.widgets') : $json['v15']['pages'][0]['widgets'] = array_values($widgets);

        $json['v15']['pages'][0]['sections'] = array_values($json['v15']['pages'][0]['sections']);
        if (collect($ops)->contains(fn ($o) => in_array($o['op'] ?? '', ['hide_section', 'show_section'], true))) {
            $before = $json['v15']['pages'][0]['sections'];
            foreach ($this->refiner->pruneDeadLinks($json['v15']) as $touched) {
                $orig = collect($before)->firstWhere('instance_id', $touched);
                $orig && $v16['edit_baseline'][$touched] ??= $orig['props'];
                $warnings[] = ['instance_id' => $touched, 'path' => '*', 'level' => 'tip', 'message' => 'Links to a hidden section were removed. Use “Restore original” to bring them back.'];
            }
        }
        $json['v15']['content_mapping']['omitted_sections'] = $this->omitted($json['v15']['content_mapping']['omitted_sections'] ?? [], $v16);
        $this->recordEdits($v16, $changed);

        $result = $this->v15->validate($json['v15'], $run);
        if ($result['errors']) {
            throw new SpecEditException($this->friendly($result['errors'][0]), $result['errors']);
        }
        $json['v15']['validation'] = $result['flags'];

        $heroTouched = collect($changed)->contains(fn ($c) => $this->isHero($json['v15'], $c['instance_id']));
        if ($heroTouched) {
            $warnings = array_merge($warnings, $this->heroVersions->syncFromSpec($json, $v16, $run));
        }

        $summary = $this->summary($changed, $json['v15']);
        $v16['change_summary'] = $summary;
        $v16['edit_draft'] = ['user_id' => $user?->id, 'at' => now()->toIso8601String()];
        $json['v16'] = $v16;

        [$spec, $coalesced] = $this->persist($base, $json, $user);

        return ['spec' => $spec, 'changed' => count($changed), 'warnings' => $warnings, 'coalesced' => $coalesced];
    }

    /** Keep the current edits as a saved layout. The draft is closed, so the next change becomes a new version and this one stays restorable. */
    public function save(DesignSpec $base, int $baseVersion, ?User $user): DesignSpec
    {
        $latest = $base->analysisRun->currentDesignSpec();
        if (! $latest || $latest->id !== $base->id || $baseVersion !== $base->version) {
            throw new SpecEditException('This page was changed somewhere else. Reload to keep working on the latest version.', [], true);
        }
        $json = $base->design_spec_json;
        $v16 = $json['v16'] ?? ['schema_version' => '1.6'];
        if (empty($v16['edit_draft'])) {
            return $base; // nothing new since the last save
        }
        $v16['edit_draft'] = null;
        $v16['saved_layout'] = ['at' => now()->toIso8601String(), 'user_id' => $user?->id];
        $v16['change_summary'] = 'Saved layout — '.preg_replace('/^Edited: /', '', (string) ($v16['change_summary'] ?? 'your edits'));
        $json['v16'] = $v16;
        $base->update(['design_spec_json' => $json]);

        return $base->fresh();
    }

    /** Restore an earlier version's content as a NEW version (history is kept). */
    public function restore(DesignSpec $current, DesignSpec $target, ?User $user): DesignSpec
    {
        if ($target->analysis_run_id !== $current->analysis_run_id) {
            throw new SpecEditException('That version belongs to a different project.');
        }
        $json = $target->design_spec_json;
        $json['v16'] = ($json['v16'] ?? ['schema_version' => '1.6']);
        $json['v16']['change_summary'] = "Restored version {$target->version}";
        $json['v16']['edit_draft'] = null; // a restore is always its own version

        return $this->versioner->create($target, $json); // the target's assets, so the restored page and its asset rows agree
    }

    // ---------------------------------------------------------------- operations

    private function setText(array &$section, array $op, array &$v16, array &$warnings): void
    {
        $path = (string) ($op['path'] ?? '');
        $rule = $this->ruleAt($section, $path);
        if (! $rule || ! in_array($rule['type'], ['string', 'text'], true)) {
            throw new SpecEditException('That text can’t be edited.', ["{$section['instance_id']}: {$path} is not an editable text field"]);
        }
        $this->baseline($section, $v16);
        $raw = is_string($op['value'] ?? null) ? $op['value'] : '';
        $value = $this->clean($raw, $rule['type'] === 'text');
        if ($value !== trim($raw)) {
            $warnings[] = ['instance_id' => $section['instance_id'], 'path' => $path, 'level' => 'tip', 'message' => 'Formatting and markup were removed — text is kept plain so it always renders safely.'];
        }
        $max = (int) ($rule['max'] ?? 800);
        if (mb_strlen($value) > $max) {
            throw new SpecEditException("That’s too long for this spot (maximum {$max} characters).", ["{$section['instance_id']}: {$path} exceeds {$max} characters"]);
        }
        if ($value === '') {
            if ($rule['required'] ?? false) {
                throw new SpecEditException('This can’t be left empty.', ["{$section['instance_id']}: {$path} is required"]);
            }
            Arr::forget($section['props'], $path);
        } else {
            Arr::set($section['props'], $path, $value);
        }
    }

    private function setCta(array &$section, array $op, array $destinations, array &$v16): void
    {
        $path = (string) ($op['path'] ?? '');
        $rule = $this->ruleAt($section, $path);
        if (! $rule || $rule['type'] !== 'cta') {
            throw new SpecEditException('That button can’t be edited.', ["{$section['instance_id']}: {$path} is not a button"]);
        }
        $this->baseline($section, $v16);
        $label = $this->clean((string) ($op['label'] ?? ''), false);
        $current = Arr::get($section['props'], $path);
        if ($label === '') {
            if ($rule['required'] ?? false) {
                throw new SpecEditException('A button needs a label.', ["{$section['instance_id']}: {$path} label is required"]);
            }
            Arr::forget($section['props'], $path);

            return;
        }
        $href = $current['href'] ?? null;
        if (! empty($op['destination_key'])) {
            $dest = $this->destinations->resolve((string) $op['destination_key'], $destinations);
            if (! $dest) {
                throw new SpecEditException('Pick a destination from the list.', ["{$section['instance_id']}: {$path} destination is not one of this page's destinations"]);
            }
            $href = $dest['destination'];
        } elseif (! empty($op['href'])) {
            $href = trim((string) $op['href']); // the user's own link; ContentValidator enforces https/mailto/tel/#/ only
        }
        if (! $href) {
            throw new SpecEditException('A button needs somewhere to go.', ["{$section['instance_id']}: {$path} has no destination"]);
        }
        Arr::set($section['props'], $path, ['label' => $label, 'href' => $href]);
    }

    private function setImage(array &$section, array $op, $run, array &$v16): void
    {
        $path = (string) ($op['path'] ?? '');
        $rule = $this->ruleAt($section, $path);
        if (! $rule || $rule['type'] !== 'image') {
            throw new SpecEditException('That picture can’t be changed.', ["{$section['instance_id']}: {$path} is not an image slot"]);
        }
        $assetId = (string) ($op['asset_id'] ?? '');
        $asset = $run->websiteAssets()->where('asset_key', $assetId)->whereNotNull('stored_path')->first();
        if (! $asset) {
            throw new SpecEditException('That image isn’t available for this project.', ["{$section['instance_id']}: asset {$assetId} does not belong to this project"]);
        }
        $this->baseline($section, $v16);
        $alt = $this->clean((string) ($op['alt'] ?? ''), false) ?: ($asset->alt_text ?: 'Image');
        Arr::set($section['props'], $path, ['asset_id' => $assetId, 'alt' => mb_substr($alt, 0, 200)]);
        $this->syncAssetBindings($section);
    }

    private function clearImage(array &$section, array $op, array &$v16): void
    {
        $path = (string) ($op['path'] ?? '');
        $rule = $this->ruleAt($section, $path);
        if (! $rule || $rule['type'] !== 'image') {
            throw new SpecEditException('That picture can’t be changed.', ["{$section['instance_id']}: {$path} is not an image slot"]);
        }
        $this->baseline($section, $v16);
        Arr::forget($section['props'], $path);
        $this->syncAssetBindings($section);
    }

    private function hide(array &$sections, int $idx, array $json, array &$v16): void
    {
        $section = $sections[$idx];
        $def = $this->blueprintDef($json['v15'], $section['instance_id']);
        if (! $def || ! ($def['optional'] ?? false) || ($def['component_id'] ?? '') === 'hero' || $section['component_id'] === 'navigation' || $section['component_id'] === 'footer') {
            throw new SpecEditException('This section is part of the layout and can’t be hidden.', ["{$section['instance_id']} is required"]);
        }
        $v16['hidden_sections'][$section['instance_id']] = ['index' => $idx, 'section' => $section];
        array_splice($sections, $idx, 1);
    }

    private function show(array &$sections, string $id, array &$v16): void
    {
        $hidden = $v16['hidden_sections'][$id] ?? null;
        if (! $hidden) {
            throw new SpecEditException('That section is not hidden.');
        }
        array_splice($sections, min((int) $hidden['index'], count($sections)), 0, [$hidden['section']]);
        unset($v16['hidden_sections'][$id]);
        if ($v16['hidden_sections'] === []) {
            unset($v16['hidden_sections']);
        }
    }

    /** @param array<int, array<string, mixed>> $widgets @param array<int, array<string, mixed>> $sections */
    private function widgetOp(string $kind, array $op, array &$widgets, array $sections, $run, array &$changed): void
    {
        $find = function (string $wid) use (&$widgets) {
            foreach ($widgets as $i => $w) {
                if ($w['id'] === $wid) {
                    return $i;
                }
            }
            throw new SpecEditException('That add-on is no longer on the page.');
        };
        $ids = array_column($sections, 'instance_id');
        $after = function ($v) use ($ids) {
            $v = (string) $v;
            if ($v !== 'top' && ! in_array($v, $ids, true)) {
                throw new SpecEditException('Pick a place on the page from the list.');
            }

            return $v;
        };
        switch ($kind) {
            case 'add_widget':
                $type = (string) ($op['type'] ?? '');
                $def = $this->widgets->types()[$type] ?? throw new SpecEditException('Unknown add-on.');
                if (count($widgets) >= WidgetCatalog::MAX_WIDGETS) {
                    throw new SpecEditException('This page already has the maximum number of add-ons ('.WidgetCatalog::MAX_WIDGETS.').');
                }
                if (! empty($def['single']) && collect($widgets)->contains('type', $type)) {
                    throw new SpecEditException("“{$def['label']}” is already on this page — edit it from the Add-ons list.");
                }
                $config = $this->widgets->clean($type, array_merge($this->widgets->defaults($type), (array) ($op['config'] ?? [])), $run);
                $wid = $this->widgets->newId();
                $widgets[] = ['id' => $wid, 'type' => $type, 'after' => $def['place'] === 'inline' ? $after($op['after'] ?? $op['instance_id'] ?? 'top') : null, 'config' => $config];
                $changed[] = ['instance_id' => $wid, 'path' => '#widget', 'kind' => 'add_widget'];
                break;
            case 'update_widget':
                $i = $find((string) ($op['widget_id'] ?? ''));
                $widgets[$i]['config'] = $this->widgets->clean($widgets[$i]['type'], (array) ($op['config'] ?? []), $run, (array) $widgets[$i]['config']);
                $changed[] = ['instance_id' => $widgets[$i]['id'], 'path' => '#widget', 'kind' => 'update_widget'];
                break;
            case 'move_widget':
                $i = $find((string) ($op['widget_id'] ?? ''));
                if (($this->widgets->types()[$widgets[$i]['type']]['place'] ?? '') !== 'inline') {
                    throw new SpecEditException('This add-on stays where it is.');
                }
                // Where: after a section (or at the top), and — when several add-ons share that spot — right before the named add-on, else last.
                $slot = $after($op['after'] ?? '');
                $before = (string) ($op['before'] ?? '');
                $moved = $widgets[$i];
                $changed[] = ['instance_id' => $moved['id'], 'path' => '#widget', 'kind' => 'move_widget'];
                array_splice($widgets, $i, 1);
                $widgets = array_values($widgets);
                $moved['after'] = $slot;
                $pos = count($widgets);
                if ($before !== '' && $before !== $moved['id']) {
                    foreach ($widgets as $k => $x) {
                        if ($x['id'] === $before) {
                            if (($x['after'] ?? 'top') !== $slot) {
                                throw new SpecEditException('That place on the page changed. Try the move again.');
                            }
                            $pos = $k;
                            break;
                        }
                    }
                }
                array_splice($widgets, $pos, 0, [$moved]);
                break;
            case 'remove_widget':
                $i = $find((string) ($op['widget_id'] ?? ''));
                $changed[] = ['instance_id' => $widgets[$i]['id'], 'path' => '#widget', 'kind' => 'remove_widget'];
                array_splice($widgets, $i, 1);
                break;
            default:
                throw new SpecEditException('Unsupported edit.');
        }
    }

    private function setStyle(array &$section, array $op): void
    {
        $path = (string) ($op['path'] ?? '');
        $rule = $this->ruleAt($section, $path);
        if (! $rule || ! in_array($rule['type'], ['string', 'text', 'cta'], true)) {
            throw new SpecEditException('That text can’t be styled.', ["{$section['instance_id']}: {$path} is not a text or button"]);
        }
        $style = $this->overrides->cleanFieldStyle(['size' => $op['size'] ?? null, 'font' => $op['font'] ?? null]);
        $this->putField($section, $path, $style + array_intersect_key($section['overrides']['fields'][$path] ?? [], ['href' => 1]));
    }

    private function setLink(array &$section, array $op): void
    {
        $path = (string) ($op['path'] ?? '');
        $rule = $this->ruleAt($section, $path);
        if (! $rule || ! in_array($rule['type'], ['string', 'text', 'image'], true)) {
            throw new SpecEditException('A link can’t be added there. Buttons have their own link.', ["{$section['instance_id']}: {$path} cannot carry a link"]);
        }
        $href = trim((string) ($op['href'] ?? ''));
        if ($href !== '' && ! $this->overrides->validHref($href)) {
            throw new SpecEditException('That link isn’t allowed — use an https:// address, an email (mailto:), a phone number (tel:) or a section on this page (#…).', ["{$section['instance_id']}: unsafe link"]);
        }
        $cur = $section['overrides']['fields'][$path] ?? [];
        unset($cur['href']);
        $this->putField($section, $path, $href === '' ? $cur : $cur + ['href' => $href]);
    }

    private function setLayout(array &$section, array $op): void
    {
        $layout = $section['overrides']['layout'] ?? [];
        if (array_key_exists('width', $op)) {
            $w = (string) $op['width'];
            if ($w !== 'standard' && ! isset(SectionOverrides::WIDTHS[$w])) {
                throw new SpecEditException('Pick a width from the list.');
            }
            $w === 'standard' ? $layout = array_diff_key($layout, ['width' => 1]) : $layout['width'] = $w;
        }
        if (array_key_exists('split', $op)) {
            if ($op['split'] === null || $op['split'] === '') {
                unset($layout['split']);
            } else {
                $n = (int) $op['split'];
                if (! $this->overrides->supportsSplit($section['component_id'], $section['variant_id'])) {
                    throw new SpecEditException('This section isn’t split into columns.');
                }
                if ($n < SectionOverrides::SPLIT_MIN || $n > SectionOverrides::SPLIT_MAX) {
                    throw new SpecEditException('Column width must be between '.SectionOverrides::SPLIT_MIN.'% and '.SectionOverrides::SPLIT_MAX.'%.');
                }
                $layout['split'] = $n;
            }
        }
        $layout ? $section['overrides']['layout'] = $layout : Arr::forget($section['overrides'], 'layout');
        $this->tidyOverrides($section);
    }

    /** Store (or, when empty, remove) one field's presentation choices. */
    private function putField(array &$section, string $path, array $entry): void
    {
        if ($entry === []) {
            unset($section['overrides']['fields'][$path]);
        } else {
            $section['overrides']['fields'][$path] = $entry;
        }
        $this->tidyOverrides($section);
    }

    private function tidyOverrides(array &$section): void
    {
        if (empty($section['overrides']['fields'])) {
            unset($section['overrides']['fields']);
        }
        if (empty($section['overrides'])) {
            unset($section['overrides']);
        }
    }

    private function revert(array &$section, array $op, array &$v16): void
    {
        $path = $op['path'] ?? null;
        if ($path) {
            unset($section['overrides']['fields'][(string) $path]);
            $this->tidyOverrides($section);
        } else {
            unset($section['overrides']);
        }
        $original = $v16['edit_baseline'][$section['instance_id']] ?? null;
        if (! $original) {
            return; // never edited: already original
        }
        if ($path) {
            $orig = Arr::get($original, (string) $path);
            $orig === null ? Arr::forget($section['props'], $path) : Arr::set($section['props'], $path, $orig);
        } else {
            $section['props'] = $original;
        }
        $this->syncAssetBindings($section);
        $v16['user_edits'] = array_values(array_filter($v16['user_edits'] ?? [], fn ($e) => ! ($e['instance_id'] === $section['instance_id'] && ($path === null || $e['path'] === $path || str_starts_with($e['path'], $path.'.')))));
    }

    // ---------------------------------------------------------------- helpers

    /** Remember the generated wording the first time a section is touched, so "restore original" always works. */
    private function baseline(array $section, array &$v16): void
    {
        $v16['edit_baseline'][$section['instance_id']] ??= $section['props'];
    }

    private function indexOf(array $sections, string $id): ?int
    {
        foreach ($sections as $i => $s) {
            if ($s['instance_id'] === $id) {
                return $i;
            }
        }

        return null;
    }

    private function blueprintDef(array $v15, string $instanceId): ?array
    {
        $bp = $this->blueprints->find($v15['design']['blueprint_id'] ?? '');

        return $bp ? collect($bp['section_sequence'])->firstWhere('instance_id', $instanceId) : null;
    }

    private function isHero(array $v15, string $instanceId): bool
    {
        foreach ($v15['pages'][0]['sections'] as $s) {
            if ($s['instance_id'] === $instanceId) {
                return $s['component_id'] === 'hero';
            }
        }

        return false;
    }

    /** The registry rule for a dotted prop path inside a section, or null if the path is not a registered prop. */
    private function ruleAt(array $section, string $path): ?array
    {
        $level = $this->registry->props($section['component_id'], $section['variant_id']); // key => rule
        $rule = null;
        $expectKey = true; // at the start, and right after a list index, the next segment names a prop
        foreach ($path === '' ? [] : explode('.', $path) as $seg) {
            if ($expectKey) {
                $rule = $level[$seg] ?? null;
                $expectKey = false;
                if ($rule === null) {
                    return null;
                }

                continue;
            }
            switch ($rule['type'] ?? null) {
                case 'list': // "items" then an index, then a key inside that item
                    if (! ctype_digit($seg) || ! is_array($rule['items'] ?? null)) {
                        return null;
                    }
                    $level = $rule['items'];
                    $rule = null;
                    $expectKey = true;
                    break;
                case 'cta':
                    $rule = $seg === 'label' ? ['type' => 'string', 'max' => 50, 'required' => true] : null;
                    break;
                case 'image':
                    $rule = $seg === 'alt' ? ['type' => 'string', 'max' => 200] : null;
                    break;
                default:
                    return null;
            }
            if ($rule === null && ! $expectKey) {
                return null;
            }
        }

        return $expectKey ? null : $rule;
    }

    /** Plain text only: no markup, no control characters, single-line for short fields. */
    private function clean(string $raw, bool $multiline): string
    {
        $t = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200D}\x{FEFF}]/u', '', $t) ?? '';
        $t = str_replace(["\r\n", "\r"], "\n", $t);
        $t = $multiline ? preg_replace("/[ \t]+/", ' ', preg_replace("/\n{3,}/", "\n\n", $t)) : preg_replace('/\s+/u', ' ', $t);

        return trim((string) $t);
    }

    /** asset_bindings mirror the images actually present in a section's props. */
    private function syncAssetBindings(array &$section): void
    {
        $ids = [];
        array_walk_recursive($section['props'], function ($v, $k) use (&$ids) {
            $k === 'asset_id' && $ids[] = $v;
        });
        $existing = collect($section['asset_bindings'] ?? [])->keyBy('asset_id');
        $section['asset_bindings'] = array_values(array_map(fn ($id) => $existing[$id] ?? ['asset_id' => $id, 'slot' => 'image', 'role' => 'user_selected'], array_unique($ids)));
    }

    private function omitted(array $omitted, array $v16): array
    {
        $omitted = array_values(array_filter($omitted, fn ($o) => ($o['reason'] ?? '') !== 'Hidden by you.'));
        foreach (array_keys($v16['hidden_sections'] ?? []) as $id) {
            $omitted[] = ['instance_id' => $id, 'required' => false, 'reason' => 'Hidden by you.'];
        }

        return $omitted;
    }

    private function recordEdits(array &$v16, array $changed): void
    {
        $edits = collect($v16['user_edits'] ?? []);
        foreach ($changed as $c) {
            if (in_array($c['kind'], ['revert', 'hide_section', 'show_section', 'add_widget', 'update_widget', 'move_widget', 'remove_widget'], true)) {
                continue; // these undo or restructure; they are not "your wording"
            }
            $edits = $edits->reject(fn ($e) => $e['instance_id'] === $c['instance_id'] && $e['path'] === $c['path'])->push(['instance_id' => $c['instance_id'], 'path' => $c['path'], 'at' => now()->toIso8601String()]);
        }
        $v16['user_edits'] = $edits->values()->all();
    }

    private function summary(array $changed, array $v15): string
    {
        $parts = [];
        foreach ($changed as $c) {
            if (str_ends_with($c['kind'], '_widget')) {
                $parts[] = ucfirst(str_replace('_', ' ', $c['kind']));

                continue;
            }
            $comp = collect($v15['pages'][0]['sections'])->firstWhere('instance_id', $c['instance_id'])['component_id'] ?? 'section';
            $parts[] = ucfirst($comp).' '.str_replace(['_', '.'], ' ', ltrim($c['path'], '#'));
        }

        return 'Edited: '.mb_substr(implode(', ', array_unique($parts)), 0, 260);
    }

    private function friendly(string $error): string
    {
        return match (true) {
            str_contains($error, 'link target') => 'That link points at a section that isn’t on the page.',
            str_contains($error, 'unsafe') || str_contains($error, 'href') => 'That link isn’t allowed — use an https:// address, an email or a phone number.',
            str_contains($error, 'markup') => 'Please use plain text only.',
            default => 'That change doesn’t fit this design: '.$error,
        };
    }

    /** @return array{0: DesignSpec, 1: bool} */
    private function persist(DesignSpec $base, array $json, ?User $user): array
    {
        $prior = $base->design_spec_json['v16']['edit_draft'] ?? null;
        $reusable = $prior
            && ($prior['user_id'] ?? null) === $user?->id
            && now()->diffInMinutes(Carbon::parse($prior['at']), true) <= self::COALESCE_MINUTES
            && $base->visualQaReports()->doesntExist()
            && ! $base->analysisRun->deployments()->exists()
            && HeroCopyApproval::where('design_spec_id', $base->id)->doesntExist();

        if ($reusable) {
            $base->update(['design_spec_json' => $json]);

            return [$base->fresh(), true];
        }

        return [$this->versioner->create($base, $json), false];
    }
}
