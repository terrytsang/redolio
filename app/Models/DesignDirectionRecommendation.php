<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignDirectionRecommendation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'analysis_run_id', 'direction_key', 'rank', 'score_json', 'rationale', 'is_recommended', 'created_at',
    ];

    protected $casts = [
        'score_json' => 'array',
        'is_recommended' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
