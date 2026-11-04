<?php

namespace App\Services\Blueprint;

use App\Services\Catalogue\DesignCatalogue;
use Illuminate\Support\Facades\Storage;

/**
 * Versioned page-blueprint definitions (database/catalogue/blueprints/*.json).
 * A blueprint is "ready" only after its previews pass automated QA — the
 * catalogue never publishes a blueprint on the strength of its JSON alone.
 */
class BlueprintCatalog
{
    private ?array $cache = null;

    public function __construct(private readonly SectionRegistry $registry, private readonly DesignCatalogue $catalogue) {}

    /** @return array<string, array> keyed by blueprint id */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = [];
            foreach (glob(database_path('catalogue/blueprints/*.json')) ?: [] as $file) {
                $bp = json_decode((string) file_get_contents($file), true);
                $this->cache[$bp['id']] = $bp;
            }
            ksort($this->cache);
        }

        return $this->cache;
    }

    public function find(string $id): ?array
    {
        return $this->all()[$id] ?? null;
    }

    /** Sample content: a direction-specific showcase when one exists, else the blueprint's default sample. */
    public function sample(string $id, ?string $directionId = null): array
    {
        foreach (array_filter([$directionId ? "{$id}.{$directionId}" : null, $id]) as $name) {
            $path = database_path("catalogue/samples/{$name}.json");
            if (is_file($path)) {
                return json_decode((string) file_get_contents($path), true);
            }
        }

        return [];
    }

    public function forDirection(string $directionId): array
    {
        return array_values(array_filter($this->all(), fn ($b) => $this->compatible($b, $directionId)));
    }

    /** A blueprint fits a direction it lists, or an imported direction whose layouts come from a direction it lists. */
    public function compatible(array $blueprint, string $directionId): bool
    {
        if (in_array($directionId, $blueprint['compatible_direction_ids'], true)) {
            return true;
        }
        $donor = $this->catalogue->importedDirection($directionId)['layout_donor'] ?? null;

        return $donor !== null && in_array($donor, $blueprint['compatible_direction_ids'], true);
    }

    /** Slot schema derived from the registry, per section instance. */
    public function contentSlotSchema(array $blueprint): array
    {
        $out = [];
        foreach ($blueprint['section_sequence'] as $s) {
            $props = $this->registry->props($s['component_id'], $s['variant_id']);
            $out[$s['instance_id']] = [
                'optional' => $s['optional'],
                'fallback' => $s['fallback'],
                'required_slots' => array_keys(array_filter($props, fn ($p) => $p['required'] ?? false)),
                'optional_slots' => array_keys(array_filter($props, fn ($p) => ! ($p['required'] ?? false))),
            ];
        }

        return $out;
    }

    public function previewManifest(string $id, string $directionId): ?array
    {
        $path = "blueprint-previews/{$id}/{$directionId}/manifest.json";
        $disk = Storage::disk('public');

        return $disk->exists($path) ? json_decode($disk->get($path), true) : null;
    }

    /**
     * 'ready' requires a passing preview QA manifest for at least one compatible direction — once this installation has captured
     * previews at all (php artisan blueprints:capture). A fresh install that has not captured any yet treats every layout as ready, so it
     * works out of the box instead of silently falling back to designs that cannot be edited.
     */
    public function effectiveStatus(array $blueprint): string
    {
        foreach ($blueprint['compatible_direction_ids'] as $directionId) {
            if (($this->previewManifest($blueprint['id'], $directionId)['passed'] ?? false) === true) {
                return 'ready';
            }
        }

        return $this->anyPreviewCaptured() ? 'draft' : 'ready';
    }

    private ?bool $anyPreview = null;

    private function anyPreviewCaptured(): bool
    {
        return $this->anyPreview ??= (glob(Storage::disk('public')->path('blueprint-previews/*/*/manifest.json')) ?: []) !== [];
    }
}
