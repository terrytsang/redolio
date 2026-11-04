<?php

namespace App\Services\Hero;

use App\Models\PositioningProfile;

/**
 * The safe fallback (PRD: "use a conservative category-and-offering message"). Built ONLY from verified or
 * user-confirmed claims, so it cannot contain an invented fact. Used when the model is unavailable or when every
 * model option fails a hard gate. It is never marked "recommended": it exists to keep the user unblocked, not to impress.
 */
class ConservativeHeroBuilder
{
    /** @param  array<string, array{action_type: string, destination: string}>  $destinations */
    public function build(PositioningProfile $profile, string $businessName, array $destinations): ?HeroCopy
    {
        $factual = $profile->evidence()->get()->filter->isFactual();
        $category = $factual->where('field', 'category')->first();
        $offerings = $factual->where('field', 'offering')->take(3);

        if (! $category && $offerings->isEmpty()) {
            return null; // nothing verified to say — the user must confirm something first
        }

        $headline = $category
            ? ucfirst(TextTools::fit($category->claim_value, 70))
            : ucfirst(TextTools::fit($offerings->first()->claim_value, 70));
        $names = $offerings->pluck('claim_value')->map(fn ($v) => mb_strtolower($v))->values();
        $audience = $factual->where('field', 'audience')->first();

        $parts = [];
        if ($names->isNotEmpty()) {
            $list = $names->count() > 1 ? $names->slice(0, -1)->implode(', ').' and '.$names->last() : $names->first();
            $parts[] = "{$businessName} provides {$list}".($audience ? ' for '.mb_strtolower($audience->claim_value) : '').'.';
        }
        $description = $parts ? TextTools::fit(implode(' ', $parts), 230) : null;
        // The description must add something the headline does not already say.
        if ($description && TextTools::overlap($headline, $description) >= 0.6) {
            $description = TextTools::fit("{$businessName}: ".($audience ? 'serving '.mb_strtolower($audience->claim_value).'.' : 'get in touch to find out more.'), 230);
        }

        $key = isset($destinations['section:contact']) ? 'section:contact' : (array_key_first($destinations) ?? null);
        $cta = $key ? ['label' => 'Contact Us', 'destination_key' => $key] + $destinations[$key] : null;
        $secondaryKey = collect(['section:products', 'section:services', 'section:features'])->first(fn ($k) => isset($destinations[$k]));
        $secondary = $secondaryKey ? ['label' => $secondaryKey === 'section:products' ? 'View Products' : 'View Services', 'destination_key' => $secondaryKey] + $destinations[$secondaryKey] : null;

        $ids = array_values(array_filter([$category?->claim_key, ...$offerings->pluck('claim_key')->all(), $audience?->claim_key]));

        return new HeroCopy(null, $headline, $description, $cta, $secondary, [], $ids, 'direct-positioning');
    }
}
