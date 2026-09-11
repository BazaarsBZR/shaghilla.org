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

    protected $casts = [
        'amount' => 'decimal:4',
        'estimated_value_min' => 'decimal:4',
        'estimated_value_max' => 'decimal:4',
        'estimated_value_confidential' => 'boolean',
        'offer_guarantee_value' => 'decimal:4',
        'event_on' => 'date',
        'publication_on' => 'date',
        'standstill_end_on' => 'date',
        'contract_on' => 'date',
        'implementation_on' => 'date',
        'announcement_at' => 'datetime',
        'submission_deadline_at' => 'datetime',
        'clarification_deadline_at' => 'datetime',
        'administrative_opening_at' => 'datetime',
        'financial_opening_at' => 'datetime',
        'source_imported_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'detail_verified_at' => 'datetime',
        'tender_documents' => 'array',
        'procurement_stages' => 'array',
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(PublicMoneySource::class, 'source_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(PublicMoneyDocument::class, 'document_id');
    }

    public function tenderStatusKey(): string
    {
        if (in_array($this->status_normalized, ['cancelled', 'awarded'], true)) {
            return $this->status_normalized;
        }

        $deadline = $this->effectiveTenderDeadline();

        if ($deadline?->isPast()) {
            return 'expired';
        }

        if ($deadline?->lte(now()->addDays(7))) {
            return 'closing_soon';
        }

        return 'open';
    }

    public function effectiveTenderDeadline()
    {
        return $this->submission_deadline_at ?: $this->administrative_opening_at;
    }

    public function tenderStatusLabel(): string
    {
        return match ($this->tenderStatusKey()) {
            'closing_soon' => 'تغلق قريباً',
            'expired' => 'انتهى موعد التقديم',
            'cancelled' => 'ملغاة',
            'awarded' => 'تم التلزيم',
            default => 'مفتوحة',
        };
    }
}
