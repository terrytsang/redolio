<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignSelection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'analysis_run_id', 'direction_key', 'direction_version', 'preferences_json', 'selected_at',
        'status', 'design_spec_id', 'error_message', 'refine_from_design_spec_id', 'visual_qa_findings_json',
        'category_id', 'blueprint_id', 'blueprint_version', 'catalogue_version', 'selection_context_json',
    ];

    protected $casts = [
        'preferences_json' => 'array',
        'visual_qa_findings_json' => 'array',
        'selection_context_json' => 'array',
        'selected_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function designSpec(): BelongsTo
    {
        return $this->belongsTo(DesignSpec::class);
    }

    public function refineFromDesignSpec(): BelongsTo
    {
        return $this->belongsTo(DesignSpec::class, 'refine_from_design_spec_id');
    }
}
