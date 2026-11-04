<?php

namespace Tests\Feature\Catalogue;

use App\Models\WebsiteAsset;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\DesignDirectionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Catalogue\Concerns\BuildsBlueprintSpecs;
use Tests\TestCase;

/** The redesign leads with the site's own lead photograph, not whichever image the model happened to pick. */
class HeroImageTest extends TestCase
{
    use BuildsBlueprintSpecs;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DesignDirectionSeeder::class);
        $this->seed(CatalogueSeeder::class);
        Storage::fake('public');
    }

    /** @param  string  $look  'photo' (varied tones), 'flat' (a decorative texture) or 'none' (no readable file) */
    private function asset($run, string $key, int $w, int $h, string $look = 'photo', string $origin = 'original'): void
    {
        if ($look !== 'none') {
            $im = imagecreatetruecolor(64, 64);
            for ($y = 0; $y < 64; $y++) {
                for ($x = 0; $x < 64; $x++) {
                    $v = $look === 'flat' ? 8 + (($x + $y) % 2) : (int) (20 + ($x / 63) * 150 + ($y / 63) * 60); // a smooth tonal spread, like a photograph
                    imagesetpixel($im, $x, $y, imagecolorallocate($im, $v, $v, $v));
                }
            }
            ob_start();
            imagepng($im);
            Storage::disk('public')->put("assets/{$key}.png", ob_get_clean());
        }
        WebsiteAsset::create(['analysis_run_id' => $run->id, 'asset_key' => $key, 'source_page_url' => 'https://x.test', 'original_url' => "https://x.test/{$key}.jpg", 'stored_path' => "assets/{$key}.png",
            'asset_type' => 'other', 'asset_origin' => $origin, 'width' => $w, 'height' => $h, 'detected_role' => 'whatever the inventory guessed', 'created_at' => now()]);
    }

    private function heroImage(callable $seed, ?string $modelPick): ?string
    {
        $run = $this->makeRun();
        $seed($run);
        [, $spec] = $this->attach($run, $this->legacySpec(['hero' => ['image_asset_key' => $modelPick]]));
        $hero = collect($spec->design_spec_json['v15']['pages'][0]['sections'])->firstWhere('instance_id', 'hero-service-split');

        return $hero['props']['image']['asset_id'] ?? null;
    }

    public function test_the_first_large_photo_on_the_site_leads_even_when_the_model_picked_another(): void
    {
        // Real anoz.co shape: plates first on the page, the model preferred a later architectural photo.
        $picked = $this->heroImage(function ($run) {
            $this->asset($run, 'plates', 1200, 904);
            $this->asset($run, 'machine', 1200, 800);
            $this->asset($run, 'lattice', 1200, 800);
        }, 'lattice');
        $this->assertSame('plates', $picked);
    }

    public function test_flat_decorative_textures_and_square_or_portrait_graphics_are_never_the_hero(): void
    {
        $picked = $this->heroImage(function ($run) {
            $this->asset($run, 'texture', 2346, 1516, 'flat');   // decorative diamond pattern
            $this->asset($run, 'portrait', 1000, 1080);          // square/portrait graphic
            $this->asset($run, 'small', 640, 400);               // too small
            $this->asset($run, 'photo', 1600, 900);
        }, 'texture');
        $this->assertSame('photo', $picked);
    }

    public function test_the_model_pick_is_kept_when_it_is_the_lead_photo_or_a_deliberate_stock_fallback(): void
    {
        $this->assertSame('first', $this->heroImage(function ($run) {
            $this->asset($run, 'first', 1600, 900);
            $this->asset($run, 'second', 1600, 900);
        }, 'first'));

        $this->assertSame('stock1', $this->heroImage(function ($run) {
            $this->asset($run, 'stock1', 1600, 900, 'photo', 'stock_photo');
            $this->asset($run, 'banner', 2346, 1516);
        }, 'stock1'));
    }

    public function test_unreadable_images_are_not_penalised_and_unknown_keys_never_bind(): void
    {
        $this->assertSame('opaque', $this->heroImage(fn ($run) => $this->asset($run, 'opaque', 1600, 900, 'none'), null));
        $this->assertNull($this->heroImage(fn ($run) => $this->asset($run, 'tiny', 300, 200), 'not-a-real-asset'));
        $this->assertNull($this->heroImage(fn ($run) => null, null));
    }

    public function test_the_hero_photo_is_not_repeated_in_the_gallery(): void
    {
        $run = $this->makeRun();
        foreach (['lead', 'p2', 'p3', 'p4'] as $k) {
            $this->asset($run, $k, 1200, 800);
        }
        [, $spec] = $this->attach($run, $this->legacySpec(['hero' => ['image_asset_key' => 'p3']]));

        $sections = collect($spec->design_spec_json['v15']['pages'][0]['sections'])->keyBy('instance_id');
        $this->assertSame('lead', $sections['hero-service-split']['props']['image']['asset_id']);
        $gallery = array_column(array_column($sections['project-gallery']['props']['items'], 'image'), 'asset_id');
        $this->assertNotContains('lead', $gallery);
        $this->assertCount(3, $gallery);
    }
}
