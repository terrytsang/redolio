<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoFixture extends Model
{
    protected $fillable = [
        'fixture_key', 'display_name', 'description', 'fixture_payload_json', 'is_active',
    ];

    protected $casts = [
        'fixture_payload_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'fixture_key';
    }
}
