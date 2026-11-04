<?php

namespace App\Services\Blueprint;

use App\Models\DesignDirection;
use App\Services\Catalogue\DesignCatalogue;
use App\Support\SampleDesignSpec;
use App\Services\Publishing\ProductionArtifactGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Renders each design direction's showcase website with the REAL blueprint renderer,
 * screenshots it in a real browser, and proves the directions are visually distinct
 * (perceptual thumbnail distance between every pair). The screenshots are what users see
 * on direction cards — never a mock-up, never a shared template.
 */
class DirectionShowcase
{
    /** Minimum mean per-channel difference (0–255) between any two hero thumbnails. */
    public const MIN_DISTANCE = 14.0;

    public function __construct(
        private readonly DesignCatalogue $catalogue,
        private readonly BlueprintCatalog $blueprints,
        private readonly BlueprintRenderer $renderer,
        private readonly ProductionArtifactGenerator $packager,
    ) {}

    /** Capture one direction. @return array{direction_id: string, blueprint_id: string, errors: string[], fonts: string[], sha256: string, thumb: array} */
    public function capture(string $directionId): array
    {
        $direction = $this->catalogue->direction($directionId) ?? throw new RuntimeException("Unknown direction [{$directionId}].");
        $blueprint = $this->blueprints->find($direction['showcase_blueprint_id']) ?? throw new RuntimeException("Showcase blueprint missing for [{$directionId}].");

        $work = storage_path('app/private/direction-showcase/'.$directionId);
        File::deleteDirectory($work);
        File::ensureDirectoryExists("{$work}/assets");

        $html = $this->renderer->render($blueprint, $this->blueprints->sample($blueprint['id'], $directionId), $directionId, ['sample_banner' => false]);
        File::put("{$work}/index.html", $this->packager->bundleFonts($html, "{$work}/assets/fonts"));

        return $this->shoot($directionId, $blueprint['id'], $work);
    }

    /** Custom (admin-created) selectable directions that have no catalogue showcase. @return DesignDirection[] */
    public function customDirections(): array
    {
        $catalogued = array_column($this->catalogue->directions(), 'id');

        return DesignDirection::selectable()->get()
            ->filter(fn (DesignDirection $d) => ! in_array($d->direction_key, $catalogued, true) && $d->hasRenderableTemplate())
            ->values()->all();
    }

    /** Capture a custom direction through its own (legacy) template — still a real page in a real browser. */
    public function captureCustom(DesignDirection $direction): array
    {
        $key = $direction->direction_key;
        $work = storage_path('app/private/direction-showcase/'.$key);
        File::deleteDirectory($work);

        $spec = SampleDesignSpec::forDirection($direction->recipe_json['template_key'] ?? $key);
        $vars = collect($direction->recipe_json['color_system'] ?? [])->mapWithKeys(fn ($hex, $role) => ['--redo-'.str_replace('_', '-', $role) => $hex])->all();
        $this->packager->generateSample($spec, $work, $vars);

        return $this->shoot($key, 'custom-template', $work);
    }

    private function shoot(string $directionId, string $blueprintId, string $work): array
    {
        $out = Process::timeout(120)->path(base_path())->run(['node', base_path('scripts/visual-qa/capture-direction.cjs'), "{$work}/index.html", "{$work}/shots"]);
        if (! $out->successful()) {
            throw new RuntimeException("Capture failed for {$directionId}: ".trim($out->errorOutput()));
        }
        $result = json_decode(trim($out->output()), true) ?: ['errors' => ['no result'], 'fonts' => []];

        $disk = Storage::disk('public');
        foreach (['hero', 'full', 'mobile'] as $shot) {
            $disk->put("direction-previews/{$directionId}/{$shot}.png", File::get("{$work}/shots/{$shot}.png"));
        }
        $sha = hash_file('sha256', "{$work}/shots/hero.png");
        $thumb = $this->thumbnail("{$work}/shots/hero.png");
        File::deleteDirectory($work);

        return ['direction_id' => $directionId, 'blueprint_id' => $blueprintId, 'errors' => $result['errors'], 'fonts' => $result['fonts'], 'sha256' => $sha, 'thumb' => $thumb];
    }

    /**
     * Pairwise perceptual distances. @param array<string, array> $captures keyed by direction id
     * @return array{min: float, closest: array{0: string, 1: string}|null, pairs: array<string, float>, passed: bool, duplicates: string[]}
     */
    public function uniqueness(array $captures): array
    {
        $ids = array_keys($captures);
        $pairs = [];
        $min = INF;
        $closest = null;
        $duplicates = [];
        for ($i = 0; $i < count($ids); $i++) {
            for ($j = $i + 1; $j < count($ids); $j++) {
                $d = $this->distance($captures[$ids[$i]]['thumb'], $captures[$ids[$j]]['thumb']);
                $pairs["{$ids[$i]}|{$ids[$j]}"] = round($d, 1);
                if ($captures[$ids[$i]]['sha256'] === $captures[$ids[$j]]['sha256']) {
                    $duplicates[] = "{$ids[$i]}|{$ids[$j]}";
                }
                if ($d < $min) {
                    [$min, $closest] = [$d, [$ids[$i], $ids[$j]]];
                }
            }
        }

        return ['min' => is_infinite($min) ? 0.0 : round($min, 1), 'closest' => $closest, 'pairs' => $pairs, 'duplicates' => $duplicates, 'passed' => $duplicates === [] && $min >= self::MIN_DISTANCE];
    }

    /** 32x20 RGB thumbnail as a flat int list. */
    private function thumbnail(string $png): array
    {
        $src = imagecreatefrompng($png);
        $thumb = imagecreatetruecolor(32, 20);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, 32, 20, imagesx($src), imagesy($src));
        $px = [];
        for ($y = 0; $y < 20; $y++) {
            for ($x = 0; $x < 32; $x++) {
                $c = imagecolorat($thumb, $x, $y);
                array_push($px, ($c >> 16) & 255, ($c >> 8) & 255, $c & 255);
            }
        }

        return $px;
    }

    private function distance(array $a, array $b): float
    {
        $sum = 0;
        foreach ($a as $i => $v) {
            $sum += abs($v - $b[$i]);
        }

        return $sum / count($a);
    }
}
