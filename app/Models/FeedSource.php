<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedSource extends Model
{
    public const DESTINATION_HOME = 'home';
    public const DESTINATION_BOTH = 'both';
    public const DESTINATION_BREAKING = 'breaking';

    protected $fillable = [
        'name',
        'url',
        'destination',
        'is_active',
        'last_fetched_at',
        'etag',
        'last_modified',
        'default_category_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    protected function destination(): Attribute
    {
        return Attribute::make(
            get: function ($value): string {
                $value = strtolower(trim((string) $value));

                return in_array($value, [self::DESTINATION_HOME, self::DESTINATION_BOTH, self::DESTINATION_BREAKING], true)
                    ? $value
                    : self::DESTINATION_BOTH;
            },
            set: function ($value): string {
                $value = strtolower(trim((string) $value));

                return in_array($value, [self::DESTINATION_HOME, self::DESTINATION_BOTH, self::DESTINATION_BREAKING], true)
                    ? $value
                    : self::DESTINATION_BOTH;
            },
        );
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
