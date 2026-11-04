<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    public $timestamps = false;

    protected $fillable = ['path', 'user_id', 'visitor_hash', 'country', 'city', 'device', 'source', 'created_at'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
