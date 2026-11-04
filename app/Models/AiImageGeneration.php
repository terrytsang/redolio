<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiImageGeneration extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'analysis_run_id', 'prompt', 'asset_key', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
