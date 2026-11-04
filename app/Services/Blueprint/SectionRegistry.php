<?php

namespace App\Services\Blueprint;

/** Typed, allowlisted lookup over config/section_registry.php. */
class SectionRegistry
{
    public function has(string $componentId, string $variantId): bool
    {
        return isset(config('section_registry')[$componentId]['variants'][$variantId]);
    }

    public function variant(string $componentId, string $variantId): array
    {
        return config('section_registry')[$componentId]['variants'][$variantId]
            ?? throw new \InvalidArgumentException("Unknown section component [{$componentId}:{$variantId}].");
    }

    public function view(string $componentId, string $variantId): string
    {
        return $this->variant($componentId, $variantId)['view'];
    }

    public function props(string $componentId, string $variantId): array
    {
        return $this->variant($componentId, $variantId)['props'];
    }

    public function anchor(string $componentId, string $variantId): ?string
    {
        return $this->variant($componentId, $variantId)['anchor'] ?? null;
    }

    /** @return array<string, string[]> component_id => variant ids */
    public function all(): array
    {
        return array_map(fn ($c) => array_keys($c['variants']), config('section_registry'));
    }
}
