<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deployment extends Model
{
    protected $fillable = [
        'analysis_run_id', 'design_spec_id', 'slug', 'deployment_number', 'status',
        'artifact_path', 'artifact_hash', 'validation_json', 'published_at',
    ];

    protected $casts = [
        'validation_json' => 'array',
        'published_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function designSpec(): BelongsTo
    {
        return $this->belongsTo(DesignSpec::class);
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }
}
