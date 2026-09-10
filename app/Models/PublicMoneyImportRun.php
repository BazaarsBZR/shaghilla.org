<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicMoneyImportRun extends Model
{
    protected $fillable = ['source_id', 'status', 'started_at', 'finished_at', 'discovered_count', 'created_count', 'updated_count', 'failed_count', 'error_summary', 'log'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'log' => 'array'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(PublicMoneySource::class, 'source_id');
    }
}
