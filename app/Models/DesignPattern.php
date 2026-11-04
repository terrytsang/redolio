<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignPattern extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'pattern_type', 'title', 'description', 'applicable_directions_json',
        'source_domains_json', 'confidence', 'scout_batch_id', 'scouted_at',
    ];

    protected $casts = [
        'applicable_directions_json' => 'array',
        'source_domains_json' => 'array',
        'scouted_at' => 'datetime',
    ];

    public function scopeForDirection($query, string $directionKey)
    {
        return $query->where(function ($q) use ($directionKey) {
            $q->whereJsonContains('applicable_directions_json', $directionKey)
                ->orWhereJsonContains('applicable_directions_json', 'general');
        });
    }
}
