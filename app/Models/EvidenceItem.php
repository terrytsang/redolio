<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'site_snapshot_id', 'evidence_key', 'page_url', 'evidence_type',
        'label', 'excerpt', 'locator_json', 'created_at',
    ];

    protected $casts = [
        'locator_json' => 'array',
        'created_at' => 'datetime',
    ];

    public function siteSnapshot(): BelongsTo
    {
        return $this->belongsTo(SiteSnapshot::class);
    }
}
