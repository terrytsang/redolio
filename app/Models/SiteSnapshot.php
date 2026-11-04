<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteSnapshot extends Model
{
    /**
     * The captured screenshot, or null. Earlier versions stored a third-party "mshots" URL that serves a
     * literal "Generating Preview…" image until (and unless) its own capture finishes — never show those.
     */
    public function realScreenshotUrl(): ?string
    {
        $url = $this->screenshot_path;

        return ($url && ! str_contains($url, 'mshots')) ? $url : null;
    }

    protected $fillable = [
        'analysis_run_id', 'snapshot_hash', 'homepage_url', 'page_count',
        'snapshot_json', 'screenshot_path', 'warnings_json',
    ];

    protected $casts = [
        'snapshot_json' => 'array',
        'warnings_json' => 'array',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SitePage::class);
    }

    public function evidenceItems(): HasMany
    {
        return $this->hasMany(EvidenceItem::class);
    }
}
