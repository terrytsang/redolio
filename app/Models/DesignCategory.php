<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignCategory extends Model
{
    protected $fillable = ['category_key', 'name', 'description', 'sort_order'];
}
