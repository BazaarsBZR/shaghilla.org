<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicMoneySource extends Model
{
    protected $fillable = ['key', 'name_ar', 'name_en', 'adapter', 'base_url', 'discovery_url', 'is_enabled', 'check_interval_minutes', 'last_checked_at', 'last_success_at', 'last_error_at', 'last_error', 'metadata'];

    protected $casts = ['is_enabled' => 'boolean', 'last_checked_at' => 'datetime', 'last_success_at' => 'datetime', 'last_error_at' => 'datetime', 'metadata' => 'array'];

    public function runs(): HasMany
    {
        return $this->hasMany(PublicMoneyImportRun::class, 'source_id');
    }
}
