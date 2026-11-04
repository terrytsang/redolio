<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NextRedoRecommendation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'analysis_run_id', 'design_spec_id', 'signal_summary_json',
        'recommendation', 'rationale', 'hypothesis_label', 'created_at',
    ];

    protected $casts = [
        'signal_summary_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function designSpec(): BelongsTo
    {
        return $this->belongsTo(DesignSpec::class);
    }
}
