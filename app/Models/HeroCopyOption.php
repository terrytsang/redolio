<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeroCopyOption extends Model
{
    public $timestamps = false;

    protected $table = 'hero_copy_options';

    /** `batch` is a string column, so a plain max() would put "9" above "10". */
    public static function latestBatch(int $runId): int
    {
        return (int) static::where('analysis_run_id', $runId)->pluck('batch')->map(fn ($b) => (int) $b)->max();
    }

    protected $fillable = ['analysis_run_id', 'positioning_profile_id', 'batch', 'strategy_id', 'eyebrow', 'headline', 'description', 'primary_cta_json', 'secondary_cta_json', 'trust_signals_json', 'evidence_ids_json', 'assumptions_json', 'risks_json', 'score_breakdown_json', 'total_score', 'recommendation', 'hard_gate_status', 'validation_json', 'rationale', 'status', 'source', 'parent_option_id', 'design_spec_id', 'created_at'];

    protected $casts = [
        'primary_cta_json' => 'array',
        'secondary_cta_json' => 'array',
        'trust_signals_json' => 'array',
        'evidence_ids_json' => 'array',
        'assumptions_json' => 'array',
        'risks_json' => 'array',
        'score_breakdown_json' => 'array',
        'validation_json' => 'array',
        'total_score' => 'float',
        'created_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PositioningProfile::class, 'positioning_profile_id');
    }

    public function renderValidations(): HasMany
    {
        return $this->hasMany(HeroRenderValidation::class);
    }
}
