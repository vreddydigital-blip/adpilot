<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrawledPage extends Model
{
    protected $fillable = [
        'analysis_job_id',
        'url',
        'final_url',
        'http_status',
        'title',
        'meta',
        'headings',
        'text_length',
        'load_time_ms',
        'content_hash',
        'features',
        'depth',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'headings' => 'array',
            'features' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(AnalysisJob::class, 'analysis_job_id');
    }

    public function features(): HasMany
    {
        return $this->hasMany(PageFeature::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PageSnapshot::class);
    }
}
