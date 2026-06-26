<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KeywordRule extends Model
{
    public const TYPE_WORKER = 'worker';
    public const TYPE_BREAKING = 'breaking';

    protected $fillable = [
        'type',
        'keyword',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
