<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteDesignProfile extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'analysis_run_id', 'profile_json', 'profile_version', 'model_identifier', 'created_at',
    ];

    protected $casts = [
        'profile_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
