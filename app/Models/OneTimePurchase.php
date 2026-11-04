<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OneTimePurchase extends Model
{
    protected $fillable = [
        'user_id', 'analysis_run_id', 'purchase_type', 'status', 'stripe_session_id', 'amount_cents', 'exports_remaining', 'saves_remaining',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(AnalysisRun::class);
    }
}
