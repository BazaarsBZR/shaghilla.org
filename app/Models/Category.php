<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'slug',
        'name_ar',
        'name_en',
        'sort_order',
        'is_system',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_system' => 'boolean',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function getNameAttribute(): string
    {
        return $this->name_en ?: $this->name_ar;
    }
}
