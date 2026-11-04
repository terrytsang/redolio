<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PositioningEvidence extends Model
{
    public $timestamps = false;

    protected $table = 'positioning_evidence';

    protected $fillable = ['positioning_profile_id', 'claim_key', 'field', 'claim_value', 'status', 'confidence', 'source_url', 'source_page_id', 'evidence_keys_json', 'evidence_summary', 'user_confirmed_at', 'created_at'];

    protected $casts = [
        'evidence_keys_json' => 'array',
        'user_confirmed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public const FACTUAL = ['verified_source', 'user_confirmed'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PositioningProfile::class, 'positioning_profile_id');
    }

    public function isFactual(): bool
    {
        return in_array($this->status, self::FACTUAL, true);
    }
}
