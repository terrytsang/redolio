<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationReport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'design_spec_id', 'evaluation_json', 'model_identifier', 'prompt_version', 'created_at',
    ];

    protected $casts = [
        'evaluation_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function designSpec(): BelongsTo
    {
        return $this->belongsTo(DesignSpec::class);
    }
}
