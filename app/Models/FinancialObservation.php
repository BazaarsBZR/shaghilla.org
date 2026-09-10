<?php

namespace App\Models;

use App\Models\Concerns\HasPublicMoneyReview;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialObservation extends Model
{
    use HasPublicMoneyReview;

    protected $table = 'public_money_financial_observations';

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:4', 'scale' => 'decimal:4', 'reporting_period_start' => 'date', 'reporting_period_end' => 'date', 'is_total' => 'boolean', 'evidence' => 'array', 'reviewed_at' => 'datetime', 'published_at' => 'datetime'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(PublicMoneySource::class, 'source_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PublicMoneyDocument::class, 'document_id');
    }
}
