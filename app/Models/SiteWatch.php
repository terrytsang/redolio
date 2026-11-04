<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SiteWatch extends Model
{
    protected $fillable = [
        'user_id', 'submitted_url', 'normalized_url', 'frequency_days',
        'is_enabled', 'next_scan_at', 'last_analysis_run_id', 'last_scanned_at', 'last_status', 'last_error',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'next_scan_at' => 'datetime',
        'last_scanned_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(WatchReport::class)->orderByDesc('scanned_at');
    }

    public function latestReport(): HasOne
    {
        return $this->hasOne(WatchReport::class)->latestOfMany('scanned_at');
    }

    public function frequencyLabel(): string
    {
        return $this->frequency_days <= 7 ? 'Once a week' : 'Once a month';
    }

    public function lastAnalysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class, 'last_analysis_run_id');
    }
}
