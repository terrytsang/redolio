<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeroGeneration extends Model
{
    public $timestamps = false;

    protected $table = 'hero_generations';

    protected $fillable = ['analysis_run_id', 'kind', 'status', 'idempotency_key', 'error_message', 'result_json', 'created_at', 'completed_at'];

    protected $casts = [
        'result_json' => 'array',
        'created_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
