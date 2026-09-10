<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicMoneyReport extends Model
{
    protected $guarded = [];

    protected $casts = ['evidence_links' => 'array', 'published_at' => 'datetime'];
}
