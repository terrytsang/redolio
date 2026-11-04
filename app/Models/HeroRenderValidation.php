<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeroRenderValidation extends Model
{
    public $timestamps = false;

    protected $table = 'hero_render_validations';

    protected $fillable = ['analysis_run_id', 'hero_copy_option_id', 'design_spec_id', 'attempt', 'viewport_json', 'font_loaded', 'headline_line_count', 'description_line_count', 'overflow_detected', 'clipping_detected', 'cta_visible', 'screenshot_path', 'validation_result', 'details_json', 'created_at'];

    protected $casts = [
        'viewport_json' => 'array',
        'details_json' => 'array',
        'font_loaded' => 'boolean',
        'overflow_detected' => 'boolean',
        'clipping_detected' => 'boolean',
        'cta_visible' => 'boolean',
        'created_at' => 'datetime',
    ];
}
