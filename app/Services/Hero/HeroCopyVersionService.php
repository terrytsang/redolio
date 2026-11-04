<?php

namespace App\Services\Hero;

use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Models\HeroCopyApproval;
use App\Models\HeroCopyOption;
use App\Models\User;
use App\Services\Blueprint\DesignSpecV15;
use App\Services\Blueprint\DesignSpecVersioner;
use App\Services\Blueprint\JsonSchemaSubset;
use App\Services\Editing\SpecEditException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Hero copy ↔ DesignSpec. Approving an option writes its copy into the hero section of a NEW spec version (the
 * previous version is never touched) and records `v16.hero_content`; user edits to the hero keep that block and the
 * legacy `hero` fields in sync and are re-checked, so the spec always says exactly what is on the page and why.
 */
class HeroCopyVersionService
{
    public function __construct(
        private readonly HeroCopyValidationService $validator,
        private readonly HeroDestinations $destinations,
        private readonly DesignSpecV15 $v15,
        private readonly DesignSpecVersioner $versioner,
        private readonly JsonSchemaSubset $schema,
    ) {}

    /** @return array{spec: DesignSpec, option: HeroCopyOption, warnings: array} */
    public function approve(AnalysisRun $run, HeroCopyOption $option, ?User $user, array $edits = []): array
    {
        if ($option->analysis_run_id !== $run->id) {
            throw new SpecEditException('That option belongs to a different project.');
        }
        if ($option->hard_gate_status !== 'pass' || in_array($option->status, ['rejected'], true)) {
            throw new SpecEditException('This option failed the factual checks and can’t be approved. Edit it or pick another.');
        }
        $spec = $run->currentDesignSpec() ?? throw new SpecEditException('There is no design to put this copy into yet.');
        $json = $spec->design_spec_json;
        if (! isset($json['v15'])) {
            throw new SpecEditException('This design cannot take hero copy.');
        }

        $profile = $run->currentPositioningProfile();
        $available = $this->destinations->forSpec($json);
        $copy = HeroCopy::fromOption($option);
        $edited = false;
        foreach (['eyebrow', 'headline', 'description'] as $f) {
            if (isset($edits[$f]) && is_string($edits[$f]) && trim($edits[$f]) !== (string) $copy->{$f}) {
                $clean = trim(strip_tags($edits[$f]));
                $copy->{$f} = $f === 'headline' ? $clean : ($clean ?: null);
                $edited = true;
            }
        }
        if ($copy->headline === '') {
            throw new SpecEditException('The headline can’t be empty.');
        }
        foreach (['primary_cta' => 'primaryCta', 'secondary_cta' => 'secondaryCta'] as $key => $prop) {
            if (isset($edits[$key]['label']) && $copy->{$prop}) {
                $copy->{$prop}['label'] = trim(strip_tags((string) $edits[$key]['label']));
                $edited = true;
            }
        }

        // Edited copy is re-checked by the same hard gates: an edit can never smuggle in an unsupported claim or a bad link.
        $validation = $profile ? $this->validator->validate($copy, $profile, $available, true) : ['factual_gate' => 'pending', 'editorial_gate' => 'pending', 'issues' => []];
        if ($validation['factual_gate'] === 'fail') {
            throw new SpecEditException('That wording makes a claim we can’t support: '.collect($validation['issues'])->where('gate', 'factual')->where('severity', 'fail')->pluck('message')->first(), array_column($validation['issues'], 'message'));
        }

        $this->writeHero($json, $copy, $available);
        $json['v16'] = $this->hydrate($json['v16'] ?? ['schema_version' => '1.6'], $copy, $profile?->id, $option->id, $edited ? 'user_edited' : 'approved', $validation);
        $json['v16']['change_summary'] = 'Hero copy approved: '.Str::limit($copy->headline, 80);
        $json['v16']['edit_draft'] = null;
        $this->syncLegacy($json, $copy);

        $result = $this->v15->validate($json['v15'], $run);
        if ($result['errors']) {
            throw new SpecEditException('That copy doesn’t fit this design: '.$result['errors'][0], $result['errors']);
        }
        $json['v15']['validation'] = $result['flags'];
        $this->assertSchema($json['v16']);

        $new = $this->versioner->create($spec, $json);
        HeroCopyOption::where('analysis_run_id', $run->id)->where('status', 'approved')->update(['status' => 'superseded']);
        $option->update(['status' => 'approved', 'design_spec_id' => $new->id]);
        HeroCopyApproval::create([
            'analysis_run_id' => $run->id, 'hero_copy_option_id' => $option->id, 'approved_by_user_id' => $user?->id, 'approval_status' => 'approved',
            'user_edits_json' => $edited ? $edits : null, 'design_spec_id' => $new->id, 'approved_at' => now(),
        ]);

        return ['spec' => $new, 'option' => $option->fresh(), 'warnings' => $this->warnings($validation)];
    }

    public function reject(HeroCopyOption $option, ?User $user): HeroCopyOption
    {
        $option->update(['status' => 'rejected']);
        HeroCopyApproval::create(['analysis_run_id' => $option->analysis_run_id, 'hero_copy_option_id' => $option->id, 'approved_by_user_id' => $user?->id, 'approval_status' => 'rejected', 'approved_at' => now()]);

        return $option->fresh();
    }

    /**
     * Called after a hand edit touches the hero section: refresh `v16.hero_content` and the legacy hero fields from
     * the section props, and re-run the gates (as guidance — the user's own words are trusted, not blocked).
     *
     * @return array<int, array{instance_id: string, path: string, level: string, message: string}>
     */
    public function syncFromSpec(array &$json, array &$v16, AnalysisRun $run): array
    {
        $hero = collect($json['v15']['pages'][0]['sections'])->firstWhere('component_id', 'hero');
        if (! $hero) {
            return [];
        }
        $p = $hero['props'];
        $toCta = fn ($c) => ! empty($c['label']) ? ['label' => $c['label'], 'destination_key' => null, 'destination' => $c['href'] ?? null] : null;
        $copy = new HeroCopy($p['eyebrow'] ?? null, (string) ($p['headline'] ?? ''), $p['subheadline'] ?? null, $toCta($p['primary_cta'] ?? null), $toCta($p['secondary_cta'] ?? null));

        $profile = $run->currentPositioningProfile();
        $validation = $profile ? $this->validator->validate($copy, $profile, [], false) : ['factual_gate' => 'pending', 'editorial_gate' => 'pending', 'issues' => []];
        $prev = $v16['hero_content'] ?? [];
        $copy->strategyId = $prev['strategy_id'] ?? 'direct-positioning';
        $copy->evidenceIds = $prev['evidence_ids'] ?? [];
        $copy->trustSignals = $prev['trust_signals'] ?? [];
        $v16 = $this->hydrate($v16, $copy, $profile?->id, $prev['copy_option_id'] ?? null, 'user_edited', $validation);
        $this->syncLegacy($json, $copy);

        return array_map(fn ($w) => ['instance_id' => $hero['instance_id'], 'path' => $w['field']] + array_diff_key($w, ['field' => 1]), $this->warnings($validation));
    }

    /** Hero copy history: every approval/rejection, newest first, with the spec version it produced. */
    public function history(AnalysisRun $run): array
    {
        return HeroCopyApproval::where('analysis_run_id', $run->id)->with('option')->orderByDesc('id')->get()->map(fn ($a) => [
            'approval_id' => $a->id, 'status' => $a->approval_status, 'at' => $a->approved_at?->toIso8601String(),
            'option_id' => $a->hero_copy_option_id, 'headline' => $a->option?->headline, 'spec_version' => $a->design_spec_id ? DesignSpec::whereKey($a->design_spec_id)->value('version') : null,
            'edited' => ! empty($a->user_edits_json),
        ])->all();
    }

    // ---------------------------------------------------------------- internals

    private function writeHero(array &$json, HeroCopy $copy, array $available): void
    {
        foreach ($json['v15']['pages'][0]['sections'] as &$s) {
            if ($s['component_id'] !== 'hero') {
                continue;
            }
            $props = $s['props'];
            $props['headline'] = $copy->headline;
            $copy->eyebrow ? $props['eyebrow'] = $copy->eyebrow : $this->forget($props, 'eyebrow');
            $copy->description ? $props['subheadline'] = $copy->description : $this->forget($props, 'subheadline');
            $props['primary_cta'] = $this->ctaProp($copy->primaryCta, $available) ?? ($props['primary_cta'] ?? null);
            ($sec = $this->ctaProp($copy->secondaryCta, $available)) ? $props['secondary_cta'] = $sec : $this->forget($props, 'secondary_cta');
            $s['props'] = array_filter($props, fn ($v) => $v !== null);
        }
    }

    private function forget(array &$a, string $k): void
    {
        unset($a[$k]);
    }

    /** @return array{label: string, href: string}|null */
    private function ctaProp(?array $cta, array $available): ?array
    {
        if (! $cta || empty($cta['label'])) {
            return null;
        }
        $href = $cta['destination'] ?? $this->destinations->resolve($cta['destination_key'] ?? null, $available)['destination'] ?? null;

        return $href ? ['label' => mb_substr((string) $cta['label'], 0, 50), 'href' => $href] : null;
    }

    private function hydrate(array $v16, HeroCopy $copy, ?int $profileId, ?int $optionId, string $status, array $validation): array
    {
        $prevValidation = $v16['hero_content']['validation'] ?? [];
        $cta = fn ($c) => $c ? ['label' => $c['label'], 'action_type' => $c['action_type'] ?? 'section_anchor', 'destination' => $c['destination'] ?? null] : null;
        $v16['schema_version'] = '1.6';
        $v16['hero_content'] = [
            'positioning_profile_id' => $profileId, 'strategy_id' => $copy->strategyId, 'eyebrow' => $copy->eyebrow, 'headline' => $copy->headline, 'description' => $copy->description,
            'primary_cta' => $cta($copy->primaryCta), 'secondary_cta' => $cta($copy->secondaryCta), 'trust_signals' => $copy->trustSignals, 'evidence_ids' => $copy->evidenceIds,
            'copy_option_id' => $optionId, 'approval_status' => $status,
            'validation' => [
                'factual_gate' => $validation['factual_gate'], 'editorial_gate' => $validation['editorial_gate'],
                // Rendered-fit results belong to the browser measurement, and describe the OLD text once the copy changes.
                'desktop_fit' => 'pending', 'tablet_fit' => 'pending', 'mobile_fit' => 'pending',
            ],
        ];

        return $v16;
    }

    private function syncLegacy(array &$json, HeroCopy $copy): void
    {
        $json['hero'] = array_merge($json['hero'] ?? [], array_filter([
            'eyebrow' => $copy->eyebrow, 'headline' => $copy->headline, 'subheadline' => $copy->description,
            'primary_cta_label' => $copy->primaryCta['label'] ?? null, 'secondary_cta_label' => $copy->secondaryCta['label'] ?? null,
        ], fn ($v) => $v !== null));
    }

    /** @return array<int, array{field: string, level: string, message: string}> */
    private function warnings(array $validation): array
    {
        return collect($validation['issues'])->map(fn ($i) => [
            'field' => $i['field'], 'level' => $i['gate'] === 'factual' && $i['severity'] === 'fail' ? 'confirm' : 'tip', 'message' => $i['message'],
        ])->values()->all();
    }

    private function assertSchema(array $v16): void
    {
        $errors = $this->schema->validate(json_decode(File::get(database_path('catalogue/schemas/design-spec-1.6.schema.json')), true), $v16, 'v16');
        if ($errors) {
            throw new SpecEditException('The hero content did not validate.', $errors);
        }
    }
}
