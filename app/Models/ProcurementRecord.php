<?php

namespace App\Models;

use App\Models\Concerns\HasPublicMoneyReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRecord extends Model
{
    use HasPublicMoneyReview;

    protected $table = 'public_money_procurements';

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:4', 'event_on' => 'date', 'publication_on' => 'date', 'standstill_end_on' => 'date', 'contract_on' => 'date', 'implementation_on' => 'date', 'evidence' => 'array', 'reviewed_at' => 'datetime', 'published_at' => 'datetime'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(PublicMoneySource::class, 'source_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PublicMoneyDocument::class, 'document_id');
    }
}
