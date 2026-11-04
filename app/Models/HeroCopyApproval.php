<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeroCopyApproval extends Model
{
    public $timestamps = false;

    protected $table = 'hero_copy_approvals';

    protected $fillable = ['analysis_run_id', 'hero_copy_option_id', 'approved_by_user_id', 'approval_status', 'user_edits_json', 'design_spec_id', 'approved_at'];

    protected $casts = [
        'user_edits_json' => 'array',
        'approved_at' => 'datetime',
    ];

    public function option(): BelongsTo
    {
        return $this->belongsTo(HeroCopyOption::class, 'hero_copy_option_id');
    }
}
