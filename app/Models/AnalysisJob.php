<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisJob extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_CRAWLING = 'crawling';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_DISPATCHED,
        self::STATUS_CRAWLING,
        self::STATUS_PROCESSING,
    ];

    protected $fillable = [
        'website_id',
        'user_id',
        'uuid',
        'status',
        'attempt',
        'dispatched_at',
        'started_at',
        'finished_at',
        'crawled_pages',
        'crawl_duration_ms',
        'failure_reason',
        'error_message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
