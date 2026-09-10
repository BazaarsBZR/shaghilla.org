<?php

namespace App\Models\Concerns;

use App\Models\PublicMoneyReviewAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPublicMoneyReview
{
    public function reviewActions(): MorphMany
    {
        return $this->morphMany(PublicMoneyReviewAction::class, 'subject');
    }

    public function transition(string $action, ?User $actor = null, ?string $reason = null): void
    {
        $before = $this->only(['review_status', 'publication_status', 'reviewed_by', 'reviewed_at', 'published_at']);

        match ($action) {
            'approve' => $this->fill([
                'review_status' => 'approved',
                'reviewed_by' => $actor?->getKey(),
                'reviewed_at' => now(),
            ]),
            'reject' => $this->fill([
                'review_status' => 'rejected',
                'publication_status' => 'draft',
                'reviewed_by' => $actor?->getKey(),
                'reviewed_at' => now(),
                'published_at' => null,
            ]),
            'publish' => $this->fill([
                'review_status' => 'approved',
                'publication_status' => 'published',
                'reviewed_by' => $actor?->getKey(),
                'reviewed_at' => $this->reviewed_at ?: now(),
                'published_at' => now(),
            ]),
            'correct' => $this->fill([
                'review_status' => 'pending',
                'publication_status' => 'corrected',
                'reviewed_by' => $actor?->getKey(),
                'reviewed_at' => now(),
                'revision' => ((int) $this->revision) + 1,
            ]),
            default => throw new \InvalidArgumentException("Unsupported review action: {$action}"),
        };

        $this->save();
        $this->reviewActions()->create([
            'action' => $action,
            'actor_id' => $actor?->getKey(),
            'reason' => $reason,
            'before' => $before,
            'after' => $this->only(['review_status', 'publication_status', 'reviewed_by', 'reviewed_at', 'published_at']),
        ]);
    }

    public function scopePubliclyVisible($query)
    {
        return $query->where('review_status', 'approved')->where('publication_status', 'published');
    }
}
