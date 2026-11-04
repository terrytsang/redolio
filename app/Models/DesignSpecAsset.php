<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignSpecAsset extends Model
{
    public $timestamps = false;

    protected $fillable = ['design_spec_id', 'asset_key', 'component_role', 'usage_json'];

    protected $casts = ['usage_json' => 'array'];

    public function designSpec(): BelongsTo
    {
        return $this->belongsTo(DesignSpec::class);
    }
}
