<?php

namespace App\Services\Hero;

use App\Models\HeroCopyOption;

/** One hero copy candidate, independent of storage. CTAs carry a destination KEY resolved against the real page. */
final class HeroCopy
{
    /**
     * @param  array{label: string, destination_key?: string, action_type?: string, destination?: string}|null  $primaryCta
     * @param  array{label: string, destination_key?: string, action_type?: string, destination?: string}|null  $secondaryCta
     * @param  array<int, array{text: string, claim_key: string}>  $trustSignals
     * @param  string[]  $evidenceIds  claim keys from the positioning profile
     */
    public function __construct(
        public ?string $eyebrow,
        public string $headline,
        public ?string $description,
        public ?array $primaryCta,
        public ?array $secondaryCta = null,
        public array $trustSignals = [],
        public array $evidenceIds = [],
        public string $strategyId = 'direct-positioning',
    ) {}

    public static function fromOption(HeroCopyOption $o): self
    {
        return new self($o->eyebrow, $o->headline, $o->description, $o->primary_cta_json, $o->secondary_cta_json, $o->trust_signals_json ?? [], $o->evidence_ids_json ?? [], $o->strategy_id);
    }

    /** The hero the legacy (v1.4/v1.5) redesign produced. */
    public static function fromLegacyHero(array $hero): self
    {
        return new self(
            $hero['eyebrow'] ?? null, (string) ($hero['headline'] ?? ''), $hero['subheadline'] ?? null,
            ! empty($hero['primary_cta_label']) ? ['label' => $hero['primary_cta_label'], 'destination_key' => null] : null,
            ! empty($hero['secondary_cta_label']) ? ['label' => $hero['secondary_cta_label'], 'destination_key' => null] : null,
        );
    }

    /** All visible hero text, for whole-copy checks. */
    public function allText(): string
    {
        return trim(implode(' ', array_filter([$this->eyebrow, $this->headline, $this->description, $this->primaryCta['label'] ?? null, $this->secondaryCta['label'] ?? null,
            implode(' ', array_column($this->trustSignals, 'text'))])));
    }

    public function toArray(): array
    {
        return ['eyebrow' => $this->eyebrow, 'headline' => $this->headline, 'description' => $this->description, 'primary_cta' => $this->primaryCta,
            'secondary_cta' => $this->secondaryCta, 'trust_signals' => $this->trustSignals, 'evidence_ids' => $this->evidenceIds, 'strategy_id' => $this->strategyId];
    }
}
