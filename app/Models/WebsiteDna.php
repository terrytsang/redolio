<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteDna extends Model
{
    protected $table = 'website_dna';

    protected $fillable = [
        'analysis_run_id', 'version', 'dna_json', 'model_identifier',
        'prompt_version', 'edited_by_user',
    ];

    protected $casts = [
        'dna_json' => 'array',
        'edited_by_user' => 'boolean',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
