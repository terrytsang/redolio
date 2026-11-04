<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelCallLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'analysis_run_id', 'stage', 'provider', 'model_identifier', 'prompt_version',
        'request_hash', 'response_status', 'latency_ms', 'input_tokens',
        'output_tokens', 'error_code', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
