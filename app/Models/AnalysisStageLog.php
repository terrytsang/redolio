<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisStageLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'analysis_run_id', 'stage', 'status', 'message_code', 'safe_message',
        'started_at', 'completed_at', 'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
