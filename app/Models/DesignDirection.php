<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignDirection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'direction_key', 'name', 'version', 'recipe_json', 'is_selectable', 'created_at',
        'primary_category_ids', 'compatible_category_ids', 'token_profile_id',
        'motion_profile_id', 'replaced_by_key', 'catalogue_version',
    ];

    protected $casts = [
        'recipe_json' => 'array',
        'is_selectable' => 'boolean',
        'primary_category_ids' => 'array',
        'compatible_category_ids' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * A direction is only safe to render if it resolves to one of
     * PreviewRenderer's allowlisted templates — either directly (the
     * original seeded directions, whose direction_key IS a template key)
     * or via a captured/assigned recipe_json['template_key']. Catalog stub
     * entries (e.g. "Product SaaS") have neither and would crash
     * PreviewRenderer if ever selected — is_selectable alone doesn't
     * guarantee this, so every user-facing query must also check it.
     */
    public function hasRenderableTemplate(): bool
    {
        $templateKey = $this->recipe_json['template_key'] ?? $this->direction_key;

        return in_array($templateKey, config('design_allowlist.templates'), true);
    }

    public function scopeSelectable($query)
    {
        return $query->where('is_selectable', true);
    }
}
