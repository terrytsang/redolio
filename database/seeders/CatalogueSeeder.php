<?php

namespace Database\Seeders;

use App\Models\DesignCategory;
use App\Models\DesignDirection;
use App\Services\Catalogue\DesignCatalogue;
use Illuminate\Database\Seeder;

/**
 * Syncs the v1.5 catalogue (database/catalogue/design-catalogue.json) into the DB.
 *
 * Backward compatibility: each new direction reuses the working recipe of the
 * legacy direction whose Blade template it renders with (recipe_json
 * ['template_key']), overriding only identity/positioning. Legacy directions
 * keep working untouched and are only annotated with category metadata and
 * the key of the v1.5 direction that supersedes them.
 *
 * With config('redo.activate_v15_directions') on (default) the 12 new directions become
 * selectable and the six legacy directions they supersede are retired from selection.
 * Turning it off restores the original six.
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $catalogue = app(DesignCatalogue::class);

        foreach ($catalogue->categories() as $c) {
            DesignCategory::updateOrCreate(
                ['category_key' => $c['id']],
                ['name' => $c['name'], 'description' => $c['description'], 'sort_order' => $c['sort']]
            );
        }

        $profiles = collect($catalogue->tokenProfiles())->keyBy('id');
        $activate = (bool) config('redo.activate_v15_directions', false);

        foreach ($catalogue->directions() as $d) {
            $base = DesignDirection::where('direction_key', $d['template_key'])->first()?->recipe_json ?? [];
            $profile = $profiles->get($d['token_profile_base']);

            $recipe = array_merge($base, [
                'direction_id' => $d['id'],
                'name' => $d['name'],
                'family' => $d['primary_category_ids'][0],
                'description' => $d['positioning'],
                'best_for' => $d['recommended_for'],
                'brand_traits' => $d['visual_traits'],
                'avoid_when' => $d['avoid_when'],
                'image_treatment' => $d['imagery'],
                'template_key' => $d['template_key'],
                'version' => $d['version'],
            ]);
            if ($profile) {
                $recipe['token_profile'] = array_merge($profile, ['id' => $d['token_profile'], 'base' => $profile['id'], 'overrides' => $d['token_overrides']]);
            }

            DesignDirection::updateOrCreate(
                ['direction_key' => $d['id']],
                [
                    'name' => $d['name'],
                    'version' => $d['version'],
                    'recipe_json' => $recipe,
                    'is_selectable' => $activate,
                    'created_at' => now(),
                    'primary_category_ids' => $d['primary_category_ids'],
                    'compatible_category_ids' => $d['compatible_category_ids'],
                    'token_profile_id' => $d['token_profile'],
                    'motion_profile_id' => $d['motion_profile'],
                    'catalogue_version' => $catalogue->version(),
                ]
            );
        }

        foreach ($catalogue->legacyDirections() as $key => $meta) {
            DesignDirection::where('direction_key', $key)->update([
                'primary_category_ids' => $meta['primary_category_ids'],
                'replaced_by_key' => $meta['replaced_by'],
                'catalogue_version' => $catalogue->version(),
                // Retire from selection only; recipes stay so existing runs/previews keep rendering.
                'is_selectable' => ! $activate,
            ]);
        }
    }
}
