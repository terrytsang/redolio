<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisualQaReport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'design_spec_id', 'status', 'report_json', 'model_identifier', 'error_message', 'created_at',
    ];

    protected $casts = [
        'report_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function designSpec(): BelongsTo
    {
        return $this->belongsTo(DesignSpec::class);
    }
}
