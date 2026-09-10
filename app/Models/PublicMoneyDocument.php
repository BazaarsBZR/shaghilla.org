<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicMoneyDocument extends Model
{
    protected $fillable = ['source_id', 'import_run_id', 'title', 'document_type', 'source_url', 'resolved_url', 'mime_type', 'source_published_on', 'retrieved_at', 'content_hash', 'parser_version', 'archive_body', 'archive_encoding', 'archive_url', 'archive_size', 'extraction_status', 'metadata'];

    protected $casts = ['source_published_on' => 'date', 'retrieved_at' => 'datetime', 'metadata' => 'array'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(PublicMoneySource::class, 'source_id');
    }
}
