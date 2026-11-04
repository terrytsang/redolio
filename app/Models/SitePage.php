<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePage extends Model
{
    protected $fillable = [
        'site_snapshot_id', 'url', 'http_status', 'title', 'meta_description',
        'canonical_url', 'headings_json', 'visible_text', 'links_json',
        'ctas_json', 'forms_json', 'image_alt_json', 'fetch_status',
    ];

    protected $casts = [
        'headings_json' => 'array',
        'links_json' => 'array',
        'ctas_json' => 'array',
        'forms_json' => 'array',
        'image_alt_json' => 'array',
    ];

    public function siteSnapshot(): BelongsTo
    {
        return $this->belongsTo(SiteSnapshot::class);
    }
}
