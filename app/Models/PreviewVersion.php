<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreviewVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'analysis_run_id', 'design_spec_id', 'version_number', 'render_status', 'quality_report_json', 'created_at',
    ];

    protected $casts = [
        'quality_report_json' => 'array',
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
