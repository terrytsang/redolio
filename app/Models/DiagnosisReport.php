<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosisReport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'analysis_run_id', 'version', 'summary', 'issues_json',
        'model_identifier', 'prompt_version', 'created_at',
    ];

    protected $casts = [
        'issues_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
