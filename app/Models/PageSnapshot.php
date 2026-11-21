<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageSnapshot extends Model
{
    protected $fillable = [
        'analysis_job_id',
        'crawled_page_id',
        'device',
        'storage_path',
        'size_bytes',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'analysis_job_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CrawledPage::class, 'crawled_page_id');
    }
}
