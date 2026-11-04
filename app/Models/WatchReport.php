<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchReport extends Model
{
    protected $fillable = ['site_watch_id', 'analysis_run_id', 'status', 'headline', 'trend', 'summary_json', 'scanned_at'];

    protected $casts = ['summary_json' => 'array', 'scanned_at' => 'datetime'];

    public function watch(): BelongsTo
    {
        return $this->belongsTo(SiteWatch::class, 'site_watch_id');
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
