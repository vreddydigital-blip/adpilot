<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictiveInsight extends Model
{
    protected $fillable = [
        'website_id',
        'analysis_job_id',
        'opportunity_key',
        'priority',
        'title',
        'evidence',
        'recommendation',
        'effort',
        'direction',
        'severity',
        'impact',
        'confidence',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'severity' => 'integer',
            'impact' => 'integer',
            'confidence' => 'integer',
            'score' => 'integer',
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
