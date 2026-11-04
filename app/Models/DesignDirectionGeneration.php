<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignDirectionGeneration extends Model
{
    protected $fillable = [
        'requested_name', 'direction_key', 'source_url', 'status', 'error_message',
        'analysis_run_id', 'design_direction_id', 'requested_by',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function designDirection(): BelongsTo
    {
        return $this->belongsTo(DesignDirection::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
