<?php

namespace App\Console\Commands;

use App\Services\Blueprint\DirectionShowcase;
use App\Services\Catalogue\DesignCatalogue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Renders and screenshots every design direction's showcase website, then proves the
 * directions are visually distinct. Requires Node + Playwright (a worker/dev machine).
 */
class CaptureDirectionPreviews extends Command
{
    protected $signature = 'directions:capture {direction? : A single direction id (default: all)}';

    protected $description = 'Capture real showcase screenshots for each design direction and verify they are unique';

    public function handle(DirectionShowcase $showcase, DesignCatalogue $catalogue): int
    {
        $only = $this->argument('direction');
        $ids = $only ? [$only] : array_column($catalogue->directions(), 'id');
        $custom = collect($showcase->customDirections())->keyBy('direction_key');
        $captures = [];

        foreach ($ids as $id) {
            if ($only && $custom->has($id)) {
                continue;
            }
            $captures[$id] = $showcase->capture($id);
            $c = $captures[$id];
            $this->line(sprintf('%-18s %-24s fonts: %-2d %s', $id, $c['blueprint_id'], count($c['fonts']), $c['errors'] ? '<error>errors: '.implode('; ', array_slice($c['errors'], 0, 2)).'</error>' : '<info>ok</info>'));
        }

        // Custom (admin-created) directions are held to the same standard: a real screenshot, distinct from the rest.
        foreach ($custom as $id => $direction) {
            if ($only && $only !== $id) {
                continue;
            }
            $captures[$id] = $showcase->captureCustom($direction);
            $this->line(sprintf('%-18s %-24s custom direction %s', $id, 'custom-template', $captures[$id]['errors'] ? '<error>errors: '.implode('; ', array_slice($captures[$id]['errors'], 0, 2)).'</error>' : '<info>ok</info>'));
        }

        if (count($captures) < 2) {
            return self::SUCCESS;
        }

        $u = $showcase->uniqueness($captures);
        $this->newLine();
        $this->line(sprintf('Closest pair: %s (distance %.1f, minimum required %.1f)', $u['closest'] ? implode(' ↔ ', $u['closest']) : '-', $u['min'], DirectionShowcase::MIN_DISTANCE));
        $this->line($u['passed'] ? '<info>All directions are visually distinct.</info>' : '<error>Some directions are too similar or identical.</error>');

        $errored = array_filter($captures, fn ($c) => $c['errors']);
        Storage::disk('public')->put('direction-previews/manifest.json', json_encode([
            'captured_at' => now()->toIso8601String(), 'catalogue_version' => $catalogue->version(),
            'passed' => $u['passed'] && $errored === [], 'min_distance' => $u['min'], 'closest' => $u['closest'], 'required_distance' => DirectionShowcase::MIN_DISTANCE,
            'directions' => array_map(fn ($c) => ['blueprint_id' => $c['blueprint_id'], 'fonts' => $c['fonts'], 'sha256' => $c['sha256'], 'errors' => $c['errors']], $captures),
        ], JSON_PRETTY_PRINT));

        return $u['passed'] && $errored === [] ? self::SUCCESS : self::FAILURE;
    }
}
