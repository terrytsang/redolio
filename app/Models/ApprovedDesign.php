<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovedDesign extends Model
{
    protected $fillable = [
        'analysis_run_id', 'design_spec_id', 'approved_by_user_id', 'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
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
