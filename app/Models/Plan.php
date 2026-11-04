<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'plan_key', 'name', 'tagline', 'price_monthly_cents', 'site_limit', 'analysis_limit', 'ai_image_limit', 'ai_image_period',
        'auto_rescan_frequency_days', 'features_json', 'is_active', 'sort_order',
        'publishing_enabled', 'static_export_enabled',
    ];

    protected $casts = [
        'features_json' => 'array',
        'is_active' => 'boolean',
        'publishing_enabled' => 'boolean',
        'static_export_enabled' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function priceMonthly(): float
    {
        return $this->price_monthly_cents / 100;
    }
}
