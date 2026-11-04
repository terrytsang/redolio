<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DesignSpec extends Model
{
    protected $fillable = [
        'analysis_run_id', 'version', 'template_key', 'design_spec_json',
        'status', 'model_identifier', 'prompt_version',
    ];

    protected $casts = [
        'design_spec_json' => 'array',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function evaluationReport(): HasOne
    {
        return $this->hasOne(EvaluationReport::class);
    }

    public function specAssets(): HasMany
    {
        return $this->hasMany(DesignSpecAsset::class);
    }

    public function visualQaReports(): HasMany
    {
        return $this->hasMany(VisualQaReport::class);
    }

    public function latestVisualQaReport(): ?VisualQaReport
    {
        return $this->visualQaReports()->latest('id')->first();
    }
}
