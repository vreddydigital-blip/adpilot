<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const FORMAT_PDF = 'pdf';

    public const FORMAT_CSV = 'csv';

    public const STATUS_READY = 'ready';

    protected $fillable = [
        'user_id',
        'website_id',
        'analysis_job_id',
        'uuid',
        'title',
        'format',
        'status',
        'path',
        'filename',
        'size_bytes',
        'payload',
        'generator',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'size_bytes' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'analysis_job_id');
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
