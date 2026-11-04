<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PositioningProfile extends Model
{
    public $timestamps = false;

    protected $table = 'positioning_profiles';

    protected $fillable = ['analysis_run_id', 'version', 'business_category', 'business_model', 'target_audiences_json', 'core_offerings_json', 'customer_problems_json', 'customer_benefits_json', 'differentiators_json', 'geographic_scope_json', 'proof_points_json', 'unknowns_json', 'contradictions_json', 'existing_hero_json', 'brand_voice', 'conversion_goal', 'status', 'source_mode', 'model_identifier', 'error_message', 'created_at'];

    protected $casts = [
        'target_audiences_json' => 'array',
        'core_offerings_json' => 'array',
        'customer_problems_json' => 'array',
        'customer_benefits_json' => 'array',
        'differentiators_json' => 'array',
        'geographic_scope_json' => 'array',
        'proof_points_json' => 'array',
        'unknowns_json' => 'array',
        'contradictions_json' => 'array',
        'existing_hero_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(PositioningEvidence::class);
    }

    /** Claims that may be stated as FACTS in public copy (PRD: only verified_source and user_confirmed). */
    public function factualClaims()
    {
        return $this->evidence()->whereIn('status', ['verified_source', 'user_confirmed']);
    }
}
