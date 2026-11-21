<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadInsight extends Model
{
    protected $fillable = [
        'website_id',
        'analysis_job_id',
        'insight_key',
        'priority',
        'title',
        'evidence',
        'recommendation',
        'effort',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
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
}
