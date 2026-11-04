<?php

namespace App\Jobs;

use App\Models\DesignDirection;
use App\Models\DesignDirectionGeneration;
use App\Services\DesignDirection\GradientDeckSource;
use App\Services\DesignDirection\ReferenceScreenshot;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\MetaApi\Validation\DesignDirectionCaptureValidator;
use App\Services\Orchestration\ModelCallLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Admin "Import a DESIGN.md": reads a GradientDeck UI style, asks the AI provider to convert it into a REDOLIO Design Direction
 * recipe (the same shape the website capture produces), and saves it with a real screenshot of the site that inspired it.
 * The direction is created hidden (not selectable) so an admin reviews it before it reaches users.
 */
class ImportDesignMdDirectionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public readonly int $generationId) {}

    public function failed(?Throwable $e = null): void
    {
        DesignDirectionGeneration::where('id', $this->generationId)->whereIn('status', ['pending', 'running'])->update([
            'status' => 'failed', 'error_message' => 'The import took too long or was interrupted. Please try again.',
        ]);
    }

    public function handle(GradientDeckSource $source, MetaModelClient $meta, DesignDirectionCaptureValidator $validator, ModelCallLogger $logger, ReferenceScreenshot $screenshots): void
    {
        $generation = DesignDirectionGeneration::find($this->generationId);
        if (! $generation) {
            return;
        }
        $generation->update(['status' => 'running']);

        try {
            $style = $source->fetch($source->slugFrom($generation->source_url));
        } catch (RuntimeException $e) {
            $generation->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            return;
        }

        $context = ['name' => $style['name'], 'nickname' => $style['nickname'], 'industry' => $style['industry'], 'theme' => $style['theme'], 'palette' => $style['colors'], 'requested_name' => $generation->requested_name];
        $hash = $meta->requestHash(['stage' => 'design_direction_from_design_md', 'slug' => $style['slug'], 'md' => md5($style['markdown'])]);
        try {
            $response = $meta->generateDesignDirectionFromDesignMd($style['markdown'], $context);
            $logger->logSuccess(null, 'design_direction_from_design_md', $hash, $response);
        } catch (MetaApiException $e) {
            $logger->logFailure(null, 'design_direction_from_design_md', $hash, $e->errorCode);
            $generation->update(['status' => 'failed', 'error_message' => 'The AI provider is temporarily unavailable. Please try again shortly.']);

            return;
        } catch (Throwable $e) {
            Log::error('DESIGN.md import failed', ['generation' => $generation->id, 'error' => $e->getMessage()]);
            $generation->update(['status' => 'failed', 'error_message' => 'Something went wrong while converting this DESIGN.md. Please try again.']);

            return;
        }

        $recipe = $validator->validate($response->data, config('design_allowlist.templates'));
        $recipe['color_system'] = $this->readableColors($recipe['color_system']);

        $name = trim($generation->requested_name) !== '' ? $generation->requested_name : $style['name'].' style';
        $key = $this->uniqueKey(Str::slug($name, '_') ?: 'imported_style');
        $siteUrl = $style['host'] ? 'https://'.$style['host'].'/' : null;
        $thumbnail = $siteUrl ? $screenshots->capture($siteUrl, $key) : null;

        $direction = DesignDirection::create([
            'direction_key' => $key, 'name' => $name, 'version' => 1, 'is_selectable' => false, 'created_at' => now(),
            'recipe_json' => $recipe + [
                'direction_id' => $key, 'name' => $name, 'version' => 1,
                'preview_thumbnail' => $thumbnail,
                'source_url' => $siteUrl,
                'inspired_by' => [
                    'name' => $style['name'], 'site' => $siteUrl, 'design_md' => $style['design_md_url'], 'collection' => 'GradientDeck UI (ui.gradientdeck.com)',
                    'note' => 'A style reference only. The design belongs to the site it was captured from; REDOLIO uses it as inspiration for its own recipe and never reuses its name, copy or imagery.',
                ],
            ],
        ]);

        $generation->update(['status' => 'complete', 'design_direction_id' => $direction->id, 'direction_key' => $key, 'requested_name' => $name]);
    }

    /** Body text must stay readable whatever the AI mapped: fall back to near-black / white when the contrast is too low. */
    private function readableColors(array $c): array
    {
        if ($this->contrast($c['text'], $c['background']) < 4.5) {
            $c['text'] = $this->luminance($c['background']) > 0.5 ? '#111111' : '#F5F5F5';
        }
        if ($this->contrast($c['accent_contrast'], $c['accent']) < 4.5) {
            $c['accent_contrast'] = $this->luminance($c['accent']) > 0.5 ? '#111111' : '#FFFFFF';
        }

        return $c;
    }

    private function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        [$r, $g, $b] = array_map(fn ($v) => ($v = hexdec($v) / 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, str_split($hex, 2));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private function contrast(string $a, string $b): float
    {
        [$l1, $l2] = [$this->luminance($a), $this->luminance($b)];

        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }

    private function uniqueKey(string $base): string
    {
        $key = $base;
        for ($i = 2; DesignDirection::where('direction_key', $key)->exists(); $i++) {
            $key = $base.'_'.$i;
        }

        return $key;
    }
}
