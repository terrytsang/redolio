<?php

namespace App\Services\Catalogue;

use App\Models\DesignDirection;
use InvalidArgumentException;

/**
 * Source of truth for the v1.5 design catalogue (categories, directions,
 * token profiles). Versioned JSON in database/catalogue/, synced into MySQL by
 * CatalogueSeeder so the existing design_directions flow keeps working.
 */
class DesignCatalogue
{
    private ?array $data = null;

    public function __construct(private readonly ?string $path = null) {}

    public function version(): string
    {
        return $this->data()['catalogue_version'];
    }

    public function categories(): array
    {
        return $this->data()['categories'];
    }

    public function directions(): array
    {
        return $this->data()['directions'];
    }

    public function direction(string $id): ?array
    {
        foreach ($this->directions() as $d) {
            if ($d['id'] === $id) {
                return $d;
            }
        }

        return $this->importedDirection($id);
    }

    /** @var array<string, ?array> */
    private array $imported = [];

    /**
     * A Design Direction an admin captured or imported (a database row with a recipe) as the layout engine sees it. It borrows the
     * structure of the built-in direction that uses the same page template — its layouts, section styles and art — and brings its own
     * colours, fonts and spacing from the recipe. "layout_donor" names the built-in direction whose layouts it can use.
     */
    public function importedDirection(string $id): ?array
    {
        if (array_key_exists($id, $this->imported)) {
            return $this->imported[$id];
        }
        // The built-in directions are defined in the catalogue file and keep exactly the layouts they list.
        if (collect($this->directions())->contains(fn ($d) => $d['id'] === $id)) {
            return $this->imported[$id] = null;
        }
        $row = DesignDirection::where('direction_key', $id)->first();
        $r = $row?->recipe_json ?? [];
        $donor = ! empty($r['color_system']) ? $this->layoutDonorFor((string) ($r['template_key'] ?? '')) : null;
        if (! $row || ! $donor) {
            return $this->imported[$id] = null;
        }

        $c = $r['color_system'];
        $serif = ['fraunces', 'instrument-serif', 'cormorant-garamond', 'playfair-display', 'lora'];
        $typo = $donor['token_overrides']['typography'];
        $display = $r['typography_system']['display'] ?? 'font-sans';
        if ($display === 'font-serif' && ! in_array($typo['display'], $serif, true)) {
            $typo['display'] = 'lora';
        } elseif ($display === 'font-sans' && in_array($typo['display'], $serif, true)) {
            $typo['display'] = $typo['body'];
        }
        $overrides = [
            'color' => ['background' => $c['background'], 'surface' => $c['surface'], 'text' => $c['text'], 'muted' => $c['text_muted'],
                'primary' => $c['accent'], 'primary_contrast' => $c['accent_contrast'], 'accent' => $c['accent']],
            'typography' => $typo,
            'density' => in_array($r['spacing_system'] ?? '', ['spacious', 'balanced', 'compact'], true) ? $r['spacing_system'] : $donor['token_overrides']['density'],
        ];

        return $this->imported[$id] = array_replace_recursive($donor, [
            'id' => $id, 'name' => $row->name, 'version' => (int) $row->version, 'imported' => true, 'layout_donor' => $donor['id'],
            'positioning' => $r['description'] ?? $donor['positioning'], 'token_overrides' => $overrides,
        ]);
    }

    /** The built-in direction of this page template that has the most page layouts to offer. */
    private function layoutDonorFor(string $templateKey): ?array
    {
        $uses = [];
        foreach (glob(database_path('catalogue/blueprints/*.json')) ?: [] as $file) {
            foreach (json_decode((string) file_get_contents($file), true)['compatible_direction_ids'] ?? [] as $d) {
                $uses[$d] = ($uses[$d] ?? 0) + 1;
            }
        }
        $best = null;
        foreach ($this->directions() as $d) {
            if (($d['template_key'] ?? null) === $templateKey && ($uses[$d['id']] ?? 0) > ($best ? ($uses[$best['id']] ?? 0) : 0)) {
                $best = $d;
            }
        }

        return $best;
    }

    public function tokenProfiles(): array
    {
        return $this->data()['token_profiles'];
    }

    public function legacyDirections(): array
    {
        return $this->data()['legacy_directions'];
    }

    /**
     * Directions discoverable under a category: primary first, then compatible.
     * Discovery only — a direction outside the category remains selectable.
     */
    public function directionsForCategory(string $categoryId): array
    {
        $primary = $compatible = [];
        foreach ($this->directions() as $d) {
            if (in_array($categoryId, $d['primary_category_ids'], true)) {
                $primary[] = $d;
            } elseif (in_array($categoryId, $d['compatible_category_ids'], true)) {
                $compatible[] = $d;
            }
        }

        return array_merge($primary, $compatible);
    }

    private function data(): array
    {
        if ($this->data === null) {
            $path = $this->path ?? database_path('catalogue/design-catalogue.json');
            $decoded = json_decode((string) file_get_contents($path), true);
            if (! is_array($decoded)) {
                throw new InvalidArgumentException("Design catalogue at {$path} is not valid JSON.");
            }
            $this->data = $decoded;
        }

        return $this->data;
    }
}
