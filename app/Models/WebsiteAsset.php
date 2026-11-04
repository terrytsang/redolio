<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WebsiteAsset extends Model
{
    private const PHOTO_TYPES = ['hero_image', 'portfolio_image', 'team_portrait', 'profile_portrait'];

    /**
     * How much tonal variation the image has (standard deviation of luminance on a 24×24 thumbnail), or null when
     * it cannot be measured. Near-flat images (≈<20) are decorative textures/backgrounds, not photographs.
     */
    public function tonalVariation(): ?float
    {
        if (! $this->stored_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($this->stored_path) || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $bytes = @\Illuminate\Support\Facades\Storage::disk('public')->get($this->stored_path);
        $im = $bytes ? @imagecreatefromstring($bytes) : false;
        if (! $im) {
            return null;
        }
        $t = imagecreatetruecolor(24, 24);
        imagecopyresampled($t, $im, 0, 0, 0, 0, 24, 24, imagesx($im), imagesy($im));
        $lum = [];
        for ($y = 0; $y < 24; $y++) {
            for ($x = 0; $x < 24; $x++) {
                $c = imagecolorat($t, $x, $y);
                $lum[] = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
            }
        }
        $mean = array_sum($lum) / count($lum);

        return sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $lum)) / count($lum));
    }

    /**
     * A real photograph from the business's own site (not a logo, icon, favicon or generated/stock fallback).
     * The inventory often files large product/industry photos under "other", so size counts too.
     */
    public function isRealPhoto(): bool
    {
        if (in_array($this->asset_origin, ['stock_photo', 'ai_generated'], true) || ! $this->stored_path) {
            return false;
        }
        if (in_array($this->asset_type, self::PHOTO_TYPES, true)) {
            return true;
        }

        return $this->asset_type === 'other' && (int) $this->width >= 600 && (int) $this->height >= 400;
    }

    public $timestamps = false;

    protected $fillable = [
        'analysis_run_id', 'asset_key', 'source_page_url', 'original_url', 'stored_path',
        'asset_type', 'asset_origin', 'attribution', 'mime_type', 'has_alpha', 'file_size', 'width', 'height', 'alt_text',
        'nearby_heading', 'nearby_text', 'dom_context', 'detected_role', 'relevance_score',
        'preservation_priority', 'recommended_usage', 'recommended_action', 'usage_status',
        'rights_status', 'download_error', 'created_at',
    ];

    protected $casts = [
        'relevance_score' => 'float',
        'created_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function url(): ?string
    {
        if (! $this->stored_path) {
            return null;
        }

        return Storage::disk('public')->url($this->stored_path);
    }
}
